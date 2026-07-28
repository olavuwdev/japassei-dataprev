<?php

declare(strict_types=1);

namespace App\Services\Study;

use App\Models\StudyBadgeModel;
use App\Models\StudyDailyProgressModel;
use App\Models\StudyQuestionAttemptModel;
use App\Models\StudySessionModel;
use App\Models\StudyTaskChecklistModel;
use App\Models\StudyTaskModel;
use App\Models\StudyUserBadgeModel;
use App\Models\StudyUserSettingModel;

/**
 * Progresso diário, meta, XP e conquistas.
 *
 * Regras de XP (calculadas exclusivamente no backend):
 * - 1 XP por minuto estudado, limitado a 60 XP por tarefa/sessão;
 * - 10 XP por meta diária concluída;
 * - 5 XP por revisão concluída;
 * - 5 XP adicionais quando aproveitamento em questões >= 80%.
 */
class StudyProgressService
{
    public const XP_DAILY_GOAL   = 10;
    public const XP_REVIEW       = 5;
    public const XP_ACCURACY     = 5;
    public const XP_MINUTES_CAP  = 60;

    public function getOrCreateDaily(int $userId, ?string $date = null): array
    {
        $date  = $date ?? date('Y-m-d');
        $model = new StudyDailyProgressModel();
        $row   = $model->where('user_id', $userId)->where('progress_date', $date)->first();

        if ($row === null) {
            $settings = (new StudyUserSettingModel())->where('user_id', $userId)->first();
            $planned  = (int) ($settings['daily_goal_minutes'] ?? 60);

            $tasksPlanned = (new StudyTaskModel())
                ->where('user_id', $userId)
                ->where('scheduled_date', $date)
                ->countAllResults();

            $model->insert([
                'user_id'         => $userId,
                'progress_date'   => $date,
                'planned_minutes' => $planned,
                'tasks_planned'   => $tasksPlanned,
            ]);

            $row = $model->where('user_id', $userId)->where('progress_date', $date)->first();
        }

        return $row;
    }

    /**
     * Soma minutos estudados ao dia, credita XP de minutos (com teto por tarefa)
     * e reavalia meta diária + ofensiva.
     *
     * @return array{daily: array, xp_awarded: int, goal_met_now: bool, streak: array}
     */
    public function addStudyMinutes(int $userId, int $minutes, ?string $date = null, ?int $taskId = null): array
    {
        $date  = $date ?? date('Y-m-d');
        $daily = $this->getOrCreateDaily($userId, $date);
        $model = new StudyDailyProgressModel();

        // XP por minutos, com teto de 60 XP por tarefa (ou por sessão avulsa).
        if ($taskId !== null) {
            $task         = (new StudyTaskModel())->find($taskId);
            $alreadySpent = (int) ($task['actual_minutes'] ?? 0);
            $xpMinutes    = max(0, min(self::XP_MINUTES_CAP, $alreadySpent + $minutes) - min(self::XP_MINUTES_CAP, $alreadySpent));
        } else {
            $xpMinutes = min($minutes, self::XP_MINUTES_CAP);
        }

        $model->update($daily['id'], [
            'studied_minutes' => (int) $daily['studied_minutes'] + $minutes,
            'xp_earned'       => (int) $daily['xp_earned'] + $xpMinutes,
        ]);

        $result = $this->evaluateDailyGoal($userId, $date);

        return [
            'daily'        => $result['daily'],
            'xp_awarded'   => $xpMinutes + $result['xp_awarded'],
            'goal_met_now' => $result['goal_met_now'],
            'streak'       => $result['streak'],
        ];
    }

    public function registerTaskCompleted(int $userId, ?string $date = null): array
    {
        $date  = $date ?? date('Y-m-d');
        $daily = $this->getOrCreateDaily($userId, $date);

        (new StudyDailyProgressModel())->update($daily['id'], [
            'tasks_completed' => (int) $daily['tasks_completed'] + 1,
        ]);

        return $this->evaluateDailyGoal($userId, $date);
    }

    public function registerQuestions(int $userId, int $total, int $correct, ?string $date = null): array
    {
        $date  = $date ?? date('Y-m-d');
        $daily = $this->getOrCreateDaily($userId, $date);

        $xp = 0;
        if ($total > 0 && ($correct / $total) >= 0.8) {
            $xp = self::XP_ACCURACY;
        }

        (new StudyDailyProgressModel())->update($daily['id'], [
            'questions_total'   => (int) $daily['questions_total'] + $total,
            'questions_correct' => (int) $daily['questions_correct'] + $correct,
            'xp_earned'         => (int) $daily['xp_earned'] + $xp,
        ]);

        return ['xp_awarded' => $xp, 'daily' => $this->getOrCreateDaily($userId, $date)];
    }

    public function registerReviewCompleted(int $userId, ?string $date = null): array
    {
        $date  = $date ?? date('Y-m-d');
        $daily = $this->getOrCreateDaily($userId, $date);

        (new StudyDailyProgressModel())->update($daily['id'], [
            'reviews_completed' => (int) $daily['reviews_completed'] + 1,
            'xp_earned'         => (int) $daily['xp_earned'] + self::XP_REVIEW,
        ]);

        return ['xp_awarded' => self::XP_REVIEW, 'daily' => $this->getOrCreateDaily($userId, $date)];
    }

    /**
     * Avalia se o dia foi cumprido:
     * 1) 60 minutos (meta diária) estudados; OU
     * 2) tarefa principal concluída + >= 45 min + checklist obrigatório completo.
     * Quando cumprido pela primeira vez: +10 XP e atualização da ofensiva.
     *
     * @return array{daily: array, xp_awarded: int, goal_met_now: bool, streak: array}
     */
    public function evaluateDailyGoal(int $userId, ?string $date = null): array
    {
        $date  = $date ?? date('Y-m-d');
        $daily = $this->getOrCreateDaily($userId, $date);

        $alreadyMet = (bool) $daily['goal_met'];
        $met        = false;

        $studied = (int) $daily['studied_minutes'];
        $goal    = max(1, (int) $daily['planned_minutes']);

        if ($studied >= $goal) {
            $met = true;
        } elseif ($studied >= 45 && $this->mainTaskFullyDone($userId, $date)) {
            $met = true;
        }

        $xp     = 0;
        $streak = [];

        if ($met && ! $alreadyMet) {
            $xp = self::XP_DAILY_GOAL;
            (new StudyDailyProgressModel())->update($daily['id'], [
                'goal_met'  => 1,
                'xp_earned' => (int) $daily['xp_earned'] + $xp,
            ]);

            $streakService = service('studyStreak');
            $result        = $streakService->registerQualifiedDay($userId, $date);
            $streak        = $result['streak'];
        } elseif (! $met && $alreadyMet) {
            // Sessão editada/excluída pode desqualificar o dia: recalcular tudo.
            (new StudyDailyProgressModel())->update($daily['id'], ['goal_met' => 0]);
            $streak = service('studyStreak')->recalculate($userId);
        } else {
            $streak = service('studyStreak')->getOrCreate($userId);
        }

        return [
            'daily'        => $this->getOrCreateDaily($userId, $date),
            'xp_awarded'   => $xp,
            'goal_met_now' => $met && ! $alreadyMet,
            'streak'       => $streak,
        ];
    }

    /**
     * XP total, nível e progresso para o próximo nível.
     * Nível N exige N*100 XP acumulados a partir do anterior (progressão simples).
     */
    public function getLevelInfo(int $userId): array
    {
        $totalXp = (int) ((new StudyDailyProgressModel())
            ->selectSum('xp_earned')
            ->where('user_id', $userId)
            ->first()['xp_earned'] ?? 0);

        $level     = 1;
        $threshold = 100;
        $remaining = $totalXp;

        while ($remaining >= $threshold) {
            $remaining -= $threshold;
            $level++;
            $threshold = $level * 100;
        }

        return [
            'total_xp'      => $totalXp,
            'level'         => $level,
            'xp_into_level' => $remaining,
            'xp_for_next'   => $threshold,
            'percent'       => (int) round($remaining / $threshold * 100),
        ];
    }

    /**
     * Verifica todas as conquistas e concede as novas.
     *
     * @return list<array> conquistas recém-obtidas
     */
    public function checkBadges(int $userId): array
    {
        $badges = (new StudyBadgeModel())->where('active', 1)->findAll();
        if ($badges === []) {
            return [];
        }

        $userBadges = (new StudyUserBadgeModel())->where('user_id', $userId)->findColumn('badge_id') ?? [];
        $earned     = [];

        $stats = $this->badgeStats($userId);

        foreach ($badges as $badge) {
            if (in_array((int) $badge['id'], array_map('intval', $userBadges), true)) {
                continue;
            }

            if ($this->badgeCriteriaMet($badge['code'], $stats)) {
                (new StudyUserBadgeModel())->insert([
                    'user_id'    => $userId,
                    'badge_id'   => $badge['id'],
                    'earned_at'  => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $earned[] = $badge;
            }
        }

        return $earned;
    }

    public function getUserBadges(int $userId): array
    {
        $db = db_connect();

        return $db->table('study_badges b')
            ->select('b.*, ub.earned_at')
            ->join('study_user_badges ub', 'ub.badge_id = b.id AND ub.user_id = ' . $userId, 'left')
            ->where('b.active', 1)
            ->orderBy('b.sort_order', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function badgeStats(int $userId): array
    {
        $sessions = new StudySessionModel();

        $completedSessions = $sessions->where('user_id', $userId)->where('status', 'completed')->countAllResults();
        $totalSeconds      = (int) ($sessions->selectSum('duration_seconds')
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->first()['duration_seconds'] ?? 0);

        $questions = (new StudyQuestionAttemptModel())
            ->selectSum('questions_total')
            ->where('user_id', $userId)
            ->first();

        $streak = service('studyStreak')->getOrCreate($userId);

        $mock80 = (new StudyQuestionAttemptModel())
            ->where('user_id', $userId)
            ->groupStart()
            ->like('source', 'simulado', 'both', null, true)
            ->groupEnd()
            ->where('score_percentage >=', 80)
            ->countAllResults();

        $qualifiedDates = (new StudyDailyProgressModel())
            ->where('user_id', $userId)
            ->where('goal_met', 1)
            ->findColumn('progress_date') ?? [];

        $byWeek = [];
        foreach ($qualifiedDates as $qualifiedDate) {
            $key          = date('o-W', strtotime($qualifiedDate));
            $byWeek[$key] = ($byWeek[$key] ?? 0) + 1;
        }
        $hasFullWeek = $byWeek !== [] && max($byWeek) >= 5;

        return [
            'sessions'    => $completedSessions,
            'hours'       => $totalSeconds / 3600,
            'questions'   => (int) ($questions['questions_total'] ?? 0),
            'best_streak' => (int) $streak['best_streak'],
            'mock_80'     => $mock80 > 0,
            'full_week'   => $hasFullWeek,
        ];
    }

    private function badgeCriteriaMet(string $code, array $stats): bool
    {
        return match ($code) {
            'first_session' => $stats['sessions'] >= 1,
            'first_week'    => $stats['full_week'],
            'streak_5'      => $stats['best_streak'] >= 5,
            'streak_10'     => $stats['best_streak'] >= 10,
            'streak_30'     => $stats['best_streak'] >= 30,
            'questions_100' => $stats['questions'] >= 100,
            'questions_500' => $stats['questions'] >= 500,
            'hours_10'      => $stats['hours'] >= 10,
            'hours_50'      => $stats['hours'] >= 50,
            'mock_80'       => $stats['mock_80'],
            default         => false,
        };
    }

    /**
     * Tarefa principal do dia concluída + todos os itens obrigatórios do checklist.
     */
    private function mainTaskFullyDone(int $userId, string $date): bool
    {
        $task = (new StudyTaskModel())
            ->where('user_id', $userId)
            ->where('scheduled_date', $date)
            ->where('is_required', 1)
            ->orderBy('priority', 'ASC')
            ->orderBy('id', 'ASC')
            ->first();

        if ($task === null || $task['status'] !== 'done') {
            return false;
        }

        $pendingRequired = (new StudyTaskChecklistModel())
            ->where('task_id', $task['id'])
            ->where('is_required', 1)
            ->where('is_completed', 0)
            ->countAllResults();

        return $pendingRequired === 0;
    }
}
