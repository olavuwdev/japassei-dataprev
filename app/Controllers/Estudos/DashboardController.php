<?php

declare(strict_types=1);

namespace App\Controllers\Estudos;

use App\Controllers\BaseController;
use App\Models\StudyDailyProgressModel;
use App\Models\StudyTaskModel;

/**
 * Visão geral do módulo de estudos (seção 4 do plano).
 */
class DashboardController extends BaseController
{
    private const WEEKDAYS_PT = [
        1 => 'segunda-feira',
        2 => 'terça-feira',
        3 => 'quarta-feira',
        4 => 'quinta-feira',
        5 => 'sexta-feira',
        6 => 'sábado',
        7 => 'domingo',
    ];

    private const MONTHS_PT = [
        1  => 'janeiro',
        2  => 'fevereiro',
        3  => 'março',
        4  => 'abril',
        5  => 'maio',
        6  => 'junho',
        7  => 'julho',
        8  => 'agosto',
        9  => 'setembro',
        10 => 'outubro',
        11 => 'novembro',
        12 => 'dezembro',
    ];

    public function index(): string
    {
        $userId = $this->userId();
        $today  = date('Y-m-d');

        // Saudação + data atual em português
        $hour     = (int) date('G');
        $greeting = $hour < 12 ? 'Bom dia' : ($hour < 18 ? 'Boa tarde' : 'Boa noite');

        $userName  = (string) ($this->session->get('user')['name'] ?? '');
        $firstName = trim(explode(' ', trim($userName))[0] ?? '');

        $currentDate = sprintf(
            '%s, %d de %s de %d',
            self::WEEKDAYS_PT[(int) date('N')],
            (int) date('j'),
            self::MONTHS_PT[(int) date('n')],
            (int) date('Y')
        );

        // Dados principais via services
        $mainTask       = service('studyTask')->getMainTask($userId);
        $daily          = service('studyProgress')->getOrCreateDaily($userId, $today);
        $overview       = service('studyStatistics')->getOverview($userId);
        $reviewsPending = service('studyReview')->countPending($userId);
        $streak         = service('studyStreak')->getState($userId, $today);
        $level          = service('studyProgress')->getLevelInfo($userId);

        // Progresso diário
        $plannedMinutes = max(1, (int) $daily['planned_minutes']);
        $studiedToday   = (int) $daily['studied_minutes'];
        $dailyPercent   = min(100, (int) round($studiedToday / $plannedMinutes * 100));

        // Progresso semanal (segunda a domingo da semana corrente)
        $monday   = date('Y-m-d', strtotime('monday this week'));
        $sunday   = date('Y-m-d', strtotime($monday . ' +6 days'));
        $weekRows = (new StudyDailyProgressModel())
            ->where('user_id', $userId)
            ->where('progress_date >=', $monday)
            ->where('progress_date <=', $sunday)
            ->findAll();

        $weekMinutes   = array_sum(array_map(static fn (array $row): int => (int) $row['studied_minutes'], $weekRows));
        $weekStudyDays = max(1, count($streak['week']));
        $weeklyGoal    = $plannedMinutes * $weekStudyDays;
        $weeklyPercent = min(100, (int) round($weekMinutes / max(1, $weeklyGoal) * 100));

        // Meta semanal: dias cumpridos entre os dias de estudo configurados
        $weekDaysDone = count(array_filter(
            $streak['week'],
            static fn (array $day): bool => $day['status'] === 'done'
        ));

        // Questões de hoje
        $questionsToday   = (int) $daily['questions_total'];
        $correctToday     = (int) $daily['questions_correct'];
        $accuracyToday    = $questionsToday > 0 ? round($correctToday / $questionsToday * 100, 1) : null;

        // Próximo conteúdo: primeira tarefa pendente após hoje
        $nextTask = (new StudyTaskModel())
            ->select('study_tasks.*, s.name AS subject_name, s.color AS subject_color')
            ->join('study_subjects s', 's.id = study_tasks.subject_id')
            ->where('study_tasks.user_id', $userId)
            ->where('study_tasks.scheduled_date >', $today)
            ->whereIn('study_tasks.status', ['pending', 'in_progress'])
            ->orderBy('study_tasks.scheduled_date', 'ASC')
            ->orderBy('study_tasks.priority', 'ASC')
            ->first();

        // Últimas 5 sessões concluídas (com disciplina)
        $lastSessions = db_connect()->table('study_sessions ss')
            ->select('ss.started_at, ss.ended_at, ss.duration_seconds, ss.planned_minutes, s.name AS subject_name, s.color AS subject_color')
            ->join('study_subjects s', 's.id = ss.subject_id')
            ->where('ss.user_id', $userId)
            ->where('ss.status', 'completed')
            ->where('ss.deleted_at', null)
            ->orderBy('ss.ended_at', 'DESC')
            ->limit(5)
            ->get()
            ->getResultArray();

        // Gráficos
        $chartSubjects = service('studyStatistics')->accuracyBySubject($userId);
        $chartWeeks    = service('studyStatistics')->minutesPerWeek($userId);

        return view('estudos/dashboard', [
            'greeting'       => $greeting,
            'firstName'      => $firstName,
            'currentDate'    => $currentDate,
            'mainTask'       => $mainTask,
            'daily'          => $daily,
            'overview'       => $overview,
            'reviewsPending' => $reviewsPending,
            'streak'         => $streak,
            'level'          => $level,
            'studiedToday'   => $studiedToday,
            'plannedMinutes' => $plannedMinutes,
            'dailyPercent'   => $dailyPercent,
            'weekMinutes'    => $weekMinutes,
            'weeklyGoal'     => $weeklyGoal,
            'weeklyPercent'  => $weeklyPercent,
            'weekDaysDone'   => $weekDaysDone,
            'weekStudyDays'  => $weekStudyDays,
            'questionsToday' => $questionsToday,
            'accuracyToday'  => $accuracyToday,
            'nextTask'       => $nextTask,
            'lastSessions'   => $lastSessions,
            'chartSubjects'  => $chartSubjects,
            'chartWeeks'     => $chartWeeks,
        ]);
    }
}
