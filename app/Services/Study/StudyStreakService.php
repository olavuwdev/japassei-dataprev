<?php

declare(strict_types=1);

namespace App\Services\Study;

use App\Models\StudyDailyProgressModel;
use App\Models\StudyStreakHistoryModel;
use App\Models\StudyStreakModel;
use App\Models\StudyUserSettingModel;

/**
 * Regras da ofensiva (streak).
 *
 * - Apenas dias configurados como dias de estudo (padrão: segunda a sexta)
 *   contam para aumentar ou quebrar a ofensiva.
 * - Sábado e domingo não aumentam nem quebram; sessões extras são bem-vindas.
 * - Dia cumprido: 60min estudados OU (tarefa principal concluída + >=45min
 *   + checklist obrigatório completo) — avaliado por StudyProgressService,
 *   que grava goal_met no progresso diário e chama registerQualifiedDay().
 */
class StudyStreakService
{
    public const EVENT_STARTED      = 'started';
    public const EVENT_INCREASED    = 'increased';
    public const EVENT_MAINTAINED   = 'maintained';
    public const EVENT_BROKEN       = 'broken';
    public const EVENT_RECALCULATED = 'recalculated';
    public const EVENT_RECORD       = 'record';

    public function getOrCreate(int $userId): array
    {
        $model  = new StudyStreakModel();
        $streak = $model->where('user_id', $userId)->first();

        if ($streak === null) {
            $model->insert([
                'user_id'              => $userId,
                'current_streak'       => 0,
                'best_streak'          => 0,
                'total_qualified_days' => 0,
            ]);
            $streak = $model->where('user_id', $userId)->first();
        }

        return $streak;
    }

    /**
     * Dias de estudo configurados pelo usuário (ISO-8601: 1 = segunda ... 7 = domingo).
     *
     * @return list<int>
     */
    public function getStudyWeekdays(int $userId): array
    {
        $settings = (new StudyUserSettingModel())->where('user_id', $userId)->first();
        $days     = $settings !== null ? json_decode((string) ($settings['study_weekdays'] ?? ''), true) : null;

        if (! is_array($days) || $days === []) {
            return [1, 2, 3, 4, 5];
        }

        return array_map('intval', $days);
    }

    public function isStudyDay(string $date, array $weekdays): bool
    {
        return in_array((int) date('N', strtotime($date)), $weekdays, true);
    }

    /**
     * Dia de estudo imediatamente anterior a $date.
     */
    public function previousStudyDay(string $date, array $weekdays): string
    {
        $ts = strtotime($date);

        do {
            $ts = strtotime('-1 day', $ts);
        } while (! in_array((int) date('N', $ts), $weekdays, true));

        return date('Y-m-d', $ts);
    }

    /**
     * Dias de estudo entre duas datas (exclusivo nas pontas).
     *
     * @return list<string>
     */
    public function studyDaysBetween(string $from, string $to, array $weekdays): array
    {
        $days = [];
        $ts   = strtotime('+1 day', strtotime($from));
        $end  = strtotime($to);

        while ($ts < $end) {
            if (in_array((int) date('N', $ts), $weekdays, true)) {
                $days[] = date('Y-m-d', $ts);
            }
            $ts = strtotime('+1 day', $ts);
        }

        return $days;
    }

    /**
     * Registra um dia cumprido e atualiza a ofensiva.
     * Idempotente para o mesmo dia. Retorna o estado atualizado + eventos.
     */
    public function registerQualifiedDay(int $userId, string $date): array
    {
        $streak   = $this->getOrCreate($userId);
        $weekdays = $this->getStudyWeekdays($userId);
        $events   = [];

        // Fim de semana (ou dia fora da rotina): não aumenta nem quebra.
        if (! $this->isStudyDay($date, $weekdays)) {
            return ['streak' => $streak, 'events' => [], 'changed' => false];
        }

        $last     = $streak['last_qualified_date'];
        $current  = (int) $streak['current_streak'];
        $previous = $current;

        if ($last !== null && $last >= $date) {
            // Já contabilizado (ou data retroativa) — nada a fazer.
            return ['streak' => $streak, 'events' => [], 'changed' => false];
        }

        if ($last === null || $current === 0) {
            $current  = 1;
            $events[] = self::EVENT_STARTED;
        } else {
            $missed = $this->missedStudyDays($userId, $last, $date, $weekdays);

            if ($missed === []) {
                $current++;
                $events[] = self::EVENT_INCREASED;
            } else {
                $this->logHistory($userId, $date, $previous, 0, self::EVENT_BROKEN, sprintf(
                    'Sequência quebrada: %d dia(s) de estudo sem meta cumprida (%s).',
                    count($missed),
                    implode(', ', $missed)
                ));
                $current  = 1;
                $events[] = self::EVENT_BROKEN;
                $events[] = self::EVENT_STARTED;
            }
        }

        $update = [
            'current_streak'       => $current,
            'total_qualified_days' => (int) $streak['total_qualified_days'] + 1,
            'last_qualified_date'  => $date,
        ];

        if ($current > (int) $streak['best_streak']) {
            $update['best_streak'] = $current;
            $update['record_date'] = $date;
            if ((int) $streak['best_streak'] > 0) {
                $events[] = self::EVENT_RECORD;
            }
        }

        (new StudyStreakModel())->update($streak['id'], $update);
        $this->logHistory($userId, $date, $previous, $current, end($events) ?: self::EVENT_MAINTAINED, null);

        $fresh = $this->getOrCreate($userId);

        return ['streak' => $fresh, 'events' => $events, 'changed' => true];
    }

    /**
     * Recalcula a ofensiva a partir do histórico de progresso diário.
     * Usado após edição/exclusão de sessões, que pode desqualificar dias.
     */
    public function recalculate(int $userId): array
    {
        $weekdays = $this->getStudyWeekdays($userId);
        $rows     = (new StudyDailyProgressModel())
            ->where('user_id', $userId)
            ->where('goal_met', 1)
            ->orderBy('progress_date', 'ASC')
            ->findAll();

        $current = 0;
        $best    = 0;
        $total   = 0;
        $lastQualified = null;
        $recordDate    = null;

        foreach ($rows as $row) {
            $date = $row['progress_date'];
            if (! $this->isStudyDay($date, $weekdays)) {
                continue;
            }

            $total++;

            if ($lastQualified === null) {
                $current = 1;
            } elseif ($this->missedStudyDays($userId, $lastQualified, $date, $weekdays) === []) {
                $current++;
            } else {
                $current = 1;
            }

            if ($current > $best) {
                $best       = $current;
                $recordDate = $date;
            }

            $lastQualified = $date;
        }

        $streak = $this->getOrCreate($userId);
        (new StudyStreakModel())->update($streak['id'], [
            'current_streak'       => $current,
            'best_streak'          => $best,
            'total_qualified_days' => $total,
            'last_qualified_date'  => $lastQualified,
            'record_date'          => $recordDate,
        ]);

        $this->logHistory($userId, date('Y-m-d'), (int) $streak['current_streak'], $current, self::EVENT_RECALCULATED, 'Recálculo após alteração de sessões.');

        return $this->getOrCreate($userId);
    }

    /**
     * Estado da ofensiva para exibição: aplica quebra "virtual" quando há dias
     * de estudo perdidos entre a última data válida e hoje.
     */
    public function getState(int $userId, ?string $today = null): array
    {
        $today    = $today ?? date('Y-m-d');
        $streak   = $this->getOrCreate($userId);
        $weekdays = $this->getStudyWeekdays($userId);

        $effective   = (int) $streak['current_streak'];
        $broken      = false;
        $todayIsStudyDay = $this->isStudyDay($today, $weekdays);
        $todayQualified  = $streak['last_qualified_date'] === $today;

        if ($streak['last_qualified_date'] !== null && ! $todayQualified) {
            $missed = $this->missedStudyDays($userId, $streak['last_qualified_date'], $today, $weekdays);
            // Dias perdidos ANTES de hoje quebram; hoje ainda em aberto não quebra.
            if ($missed !== []) {
                $effective = 0;
                $broken    = true;
            }
        }

        $atRisk = $todayIsStudyDay && ! $todayQualified && $effective > 0;

        return [
            'current_streak'       => $effective,
            'stored_streak'        => (int) $streak['current_streak'],
            'best_streak'          => (int) $streak['best_streak'],
            'total_qualified_days' => (int) $streak['total_qualified_days'],
            'last_qualified_date'  => $streak['last_qualified_date'],
            'record_date'          => $streak['record_date'],
            'today_qualified'      => $todayQualified,
            'today_is_study_day'   => $todayIsStudyDay,
            'at_risk'              => $atRisk,
            'broken'               => $broken,
            'message'              => $this->motivationalMessage($effective, $todayQualified, $atRisk, $broken),
            'week'                 => $this->getWeekMap($userId, $today),
        ];
    }

    /**
     * Mapa da semana (segunda a sexta) para a representação visual.
     *
     * @return list<array{date: string, weekday: int, label: string, status: string}>
     */
    public function getWeekMap(int $userId, ?string $today = null): array
    {
        $today    = $today ?? date('Y-m-d');
        $weekdays = $this->getStudyWeekdays($userId);
        $monday   = date('Y-m-d', strtotime('monday this week', strtotime($today)));

        $progress = (new StudyDailyProgressModel())
            ->where('user_id', $userId)
            ->where('progress_date >=', $monday)
            ->where('progress_date <=', date('Y-m-d', strtotime($monday . ' +6 days')))
            ->findAll();

        $byDate = array_column($progress, null, 'progress_date');
        $labels = [1 => 'S', 2 => 'T', 3 => 'Q', 4 => 'Q', 5 => 'S', 6 => 'S', 7 => 'D'];
        $map    = [];

        foreach ($weekdays as $weekday) {
            $date = date('Y-m-d', strtotime($monday . ' +' . ($weekday - 1) . ' days'));
            $done = ! empty($byDate[$date]['goal_met']);

            if ($done) {
                $status = 'done';
            } elseif ($date === $today) {
                $status = 'today';
            } elseif ($date < $today) {
                $status = 'missed';
            } else {
                $status = 'upcoming';
            }

            $map[] = [
                'date'    => $date,
                'weekday' => $weekday,
                'label'   => $labels[$weekday],
                'status'  => $status,
            ];
        }

        return $map;
    }

    /**
     * Dias de estudo perdidos (sem meta) entre duas datas, exclusivo nas pontas.
     *
     * @return list<string>
     */
    private function missedStudyDays(int $userId, string $from, string $to, array $weekdays): array
    {
        $between = $this->studyDaysBetween($from, $to, $weekdays);

        if ($between === []) {
            return [];
        }

        $qualified = (new StudyDailyProgressModel())
            ->where('user_id', $userId)
            ->whereIn('progress_date', $between)
            ->where('goal_met', 1)
            ->findColumn('progress_date') ?? [];

        return array_values(array_diff($between, $qualified));
    }

    private function motivationalMessage(int $streak, bool $todayQualified, bool $atRisk, bool $broken): string
    {
        if ($todayQualified) {
            return 'Você concluiu sua meta de hoje.';
        }

        if ($broken) {
            return 'Continue. A consistência está construindo sua aprovação.';
        }

        if ($atRisk) {
            return 'Falta uma sessão para manter sua sequência.';
        }

        if ($streak > 0) {
            return 'Sua ofensiva está segura.';
        }

        return 'Comece hoje. Uma sessão de cada vez.';
    }

    private function logHistory(int $userId, string $date, int $previous, int $new, string $event, ?string $description): void
    {
        (new StudyStreakHistoryModel())->insert([
            'user_id'         => $userId,
            'reference_date'  => $date,
            'previous_streak' => $previous,
            'new_streak'      => $new,
            'event_type'      => $event,
            'description'     => $description,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
    }
}
