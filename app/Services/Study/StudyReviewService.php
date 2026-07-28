<?php

declare(strict_types=1);

namespace App\Services\Study;

use App\Models\StudyReviewModel;
use App\Models\StudyTaskModel;
use App\Models\StudyUserSettingModel;
use RuntimeException;

/**
 * Revisões espaçadas: geradas na primeira conclusão de um conteúdo teórico
 * nos intervalos configurados (padrão 1, 7 e 30 dias).
 */
class StudyReviewService
{
    public const STATUS_PENDING     = 'pending';
    public const STATUS_AVAILABLE   = 'available';
    public const STATUS_OVERDUE     = 'overdue';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_SKIPPED     = 'skipped';
    public const STATUS_RESCHEDULED = 'rescheduled';

    /**
     * @return list<int> intervalos em dias
     */
    public function getIntervals(int $userId): array
    {
        $settings  = (new StudyUserSettingModel())->where('user_id', $userId)->first();
        $intervals = $settings !== null ? json_decode((string) ($settings['review_intervals'] ?? ''), true) : null;

        if (! is_array($intervals) || $intervals === []) {
            return [1, 7, 30];
        }

        return array_map('intval', $intervals);
    }

    /**
     * Gera revisões para uma tarefa teórica concluída pela primeira vez.
     * Idempotente: não duplica se já houver revisões para a mesma origem
     * (ou mesmo tópico, quando definido).
     *
     * @return list<array> revisões criadas
     */
    public function generateForTask(int $userId, array $task, ?string $baseDate = null): array
    {
        if ($task['task_type'] !== 'theory') {
            return [];
        }

        $baseDate = $baseDate ?? date('Y-m-d');
        $model    = new StudyReviewModel();

        $existing = $model->where('user_id', $userId);
        if (! empty($task['topic_id'])) {
            $existing = $existing->groupStart()
                ->where('origin_task_id', $task['id'])
                ->orWhere('topic_id', $task['topic_id'])
                ->groupEnd();
        } else {
            $existing = $existing->where('origin_task_id', $task['id']);
        }

        if ($existing->countAllResults() > 0) {
            return [];
        }

        $created = [];

        foreach ($this->getIntervals($userId) as $index => $days) {
            $dueDate = date('Y-m-d', strtotime($baseDate . ' +' . $days . ' days'));

            $id = $model->insert([
                'user_id'        => $userId,
                'origin_task_id' => $task['id'],
                'subject_id'     => $task['subject_id'],
                'topic_id'       => $task['topic_id'],
                'review_number'  => $index + 1,
                'interval_days'  => $days,
                'due_date'       => $dueDate,
                'status'         => $dueDate <= date('Y-m-d') ? self::STATUS_AVAILABLE : self::STATUS_PENDING,
            ]);

            $created[] = $model->find($id);
        }

        return $created;
    }

    /**
     * Atualiza situações por data: pending -> available (hoje) / overdue (vencida).
     */
    public function refreshStatuses(int $userId): void
    {
        $today = date('Y-m-d');
        $model = new StudyReviewModel();

        $model->where('user_id', $userId)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_RESCHEDULED])
            ->where('due_date', $today)
            ->set('status', self::STATUS_AVAILABLE)
            ->update();

        $model->where('user_id', $userId)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_AVAILABLE, self::STATUS_RESCHEDULED])
            ->where('due_date <', $today)
            ->set('status', self::STATUS_OVERDUE)
            ->update();
    }

    /**
     * Revisões agrupadas para a página: hoje, atrasadas, próximas, concluídas.
     */
    public function getGrouped(int $userId): array
    {
        $this->refreshStatuses($userId);

        $today = date('Y-m-d');
        $db    = db_connect();

        $base = static fn () => $db->table('study_reviews r')
            ->select('r.*, s.name AS subject_name, s.color AS subject_color, t.name AS topic_name, tk.title AS task_title')
            ->join('study_subjects s', 's.id = r.subject_id')
            ->join('study_topics t', 't.id = r.topic_id', 'left')
            ->join('study_tasks tk', 'tk.id = r.origin_task_id', 'left')
            ->where('r.user_id', $userId)
            ->where('r.deleted_at', null);

        return [
            'today' => $base()->where('r.status', self::STATUS_AVAILABLE)
                ->where('r.due_date', $today)
                ->orderBy('r.due_date', 'ASC')->get()->getResultArray(),
            'overdue' => $base()->where('r.status', self::STATUS_OVERDUE)
                ->orderBy('r.due_date', 'ASC')->get()->getResultArray(),
            'upcoming' => $base()->whereIn('r.status', [self::STATUS_PENDING, self::STATUS_RESCHEDULED])
                ->where('r.due_date >', $today)
                ->orderBy('r.due_date', 'ASC')->limit(30)->get()->getResultArray(),
            'completed' => $base()->where('r.status', self::STATUS_COMPLETED)
                ->orderBy('r.completed_at', 'DESC')->limit(30)->get()->getResultArray(),
        ];
    }

    public function countPending(int $userId): int
    {
        $this->refreshStatuses($userId);

        return (new StudyReviewModel())
            ->where('user_id', $userId)
            ->whereIn('status', [self::STATUS_AVAILABLE, self::STATUS_OVERDUE])
            ->countAllResults();
    }

    /**
     * Conclui uma revisão registrando questões, dificuldade e observação.
     */
    public function complete(int $userId, int $reviewId, array $data): array
    {
        $review = $this->findOwned($userId, $reviewId);

        if ($review['status'] === self::STATUS_COMPLETED) {
            throw new RuntimeException('Esta revisão já foi concluída.');
        }

        $total   = max(0, (int) ($data['questions_total'] ?? 0));
        $correct = max(0, (int) ($data['questions_correct'] ?? 0));

        if ($correct > $total) {
            throw new RuntimeException('A quantidade de acertos não pode ser maior que o total de questões.');
        }

        (new StudyReviewModel())->update($reviewId, [
            'status'            => self::STATUS_COMPLETED,
            'questions_total'   => $total,
            'questions_correct' => $correct,
            'difficulty'        => isset($data['difficulty']) ? (int) $data['difficulty'] : null,
            'notes'             => $data['notes'] ?? null,
            'completed_at'      => date('Y-m-d H:i:s'),
        ]);

        /** @var StudyProgressService $progress */
        $progress = service('studyProgress');
        $result   = $progress->registerReviewCompleted($userId);

        $xp = $result['xp_awarded'];
        if ($total > 0) {
            $questionResult = $progress->registerQuestions($userId, $total, $correct);
            $xp += $questionResult['xp_awarded'];
        }

        $badges = $progress->checkBadges($userId);

        return [
            'review'     => (new StudyReviewModel())->find($reviewId),
            'xp_awarded' => $xp,
            'new_badges' => $badges,
        ];
    }

    public function reschedule(int $userId, int $reviewId, string $newDate): array
    {
        $review = $this->findOwned($userId, $reviewId);

        if ($review['status'] === self::STATUS_COMPLETED) {
            throw new RuntimeException('Revisões concluídas não podem ser reagendadas.');
        }

        if (strtotime($newDate) === false || $newDate < date('Y-m-d')) {
            throw new RuntimeException('Informe uma data futura válida.');
        }

        (new StudyReviewModel())->update($reviewId, [
            'due_date' => $newDate,
            'status'   => $newDate === date('Y-m-d') ? self::STATUS_AVAILABLE : self::STATUS_RESCHEDULED,
        ]);

        return (new StudyReviewModel())->find($reviewId);
    }

    public function skip(int $userId, int $reviewId): array
    {
        $review = $this->findOwned($userId, $reviewId);

        if ($review['status'] === self::STATUS_COMPLETED) {
            throw new RuntimeException('Revisões concluídas não podem ser ignoradas.');
        }

        (new StudyReviewModel())->update($reviewId, ['status' => self::STATUS_SKIPPED]);

        return (new StudyReviewModel())->find($reviewId);
    }

    private function findOwned(int $userId, int $reviewId): array
    {
        $review = (new StudyReviewModel())->where('user_id', $userId)->find($reviewId);

        if ($review === null) {
            throw new RuntimeException('Revisão não encontrada.');
        }

        return $review;
    }
}
