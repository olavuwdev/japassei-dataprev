<?php

declare(strict_types=1);

namespace App\Services\Study;

use App\Models\StudyQuestionAttemptModel;
use RuntimeException;

/**
 * Indicadores, gráficos e registro de desempenho em questões.
 * Todos os cálculos são feitos no backend.
 */
class StudyStatisticsService
{
    /**
     * Indicadores gerais. Filtros: date_from, date_to, subject_id.
     */
    public function getOverview(int $userId, array $filters = []): array
    {
        $db = db_connect();

        $sessions = $db->table('study_sessions')
            ->select('COUNT(*) AS total_sessions, COALESCE(SUM(duration_seconds), 0) AS total_seconds', false)
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->where('deleted_at', null);
        $this->applyDateFilter($sessions, 'started_at', $filters, true);
        if (! empty($filters['subject_id'])) {
            $sessions->where('subject_id', (int) $filters['subject_id']);
        }
        $sessionRow = $sessions->get()->getRowArray();

        $questions = $db->table('study_question_attempts')
            ->select('COALESCE(SUM(questions_total), 0) AS total, COALESCE(SUM(questions_correct), 0) AS correct', false)
            ->where('user_id', $userId)
            ->where('deleted_at', null);
        $this->applyDateFilter($questions, 'attempt_date', $filters);
        if (! empty($filters['subject_id'])) {
            $questions->where('subject_id', (int) $filters['subject_id']);
        }
        $questionRow = $questions->get()->getRowArray();

        $reviews = $db->table('study_reviews')
            ->select("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
                      SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) AS overdue", false)
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        $days = $db->table('study_daily_progress')
            ->select('COUNT(*) AS days, COALESCE(SUM(studied_minutes), 0) AS minutes', false)
            ->where('user_id', $userId)
            ->where('studied_minutes >', 0);
        $this->applyDateFilter($days, 'progress_date', $filters);
        $daysRow = $days->get()->getRowArray();

        $totalSeconds = (int) ($sessionRow['total_seconds'] ?? 0);
        $totalQ       = (int) ($questionRow['total'] ?? 0);
        $correctQ     = (int) ($questionRow['correct'] ?? 0);
        $activeDays   = (int) ($daysRow['days'] ?? 0);
        $totalMinutes = (int) ($daysRow['minutes'] ?? 0);

        return [
            'total_hours'       => round($totalSeconds / 3600, 1),
            'total_sessions'    => (int) ($sessionRow['total_sessions'] ?? 0),
            'daily_average'     => $activeDays > 0 ? (int) round($totalMinutes / $activeDays) : 0,
            'weekly_average'    => $activeDays > 0 ? (int) round($totalMinutes / max(1, ceil($activeDays / 5))) : 0,
            'questions_total'   => $totalQ,
            'accuracy'          => $totalQ > 0 ? round($correctQ / $totalQ * 100, 1) : 0.0,
            'reviews_completed' => (int) ($reviews['completed'] ?? 0),
            'reviews_overdue'   => (int) ($reviews['overdue'] ?? 0),
            'best_subject'      => $this->subjectExtreme($userId, $filters, true),
            'worst_subject'     => $this->subjectExtreme($userId, $filters, false),
            'top_contents'      => $this->topContents($userId, $filters),
            'error_contents'    => $this->errorContents($userId, $filters),
        ];
    }

    /**
     * Registra desempenho em questões, validando os totais.
     * acertos + erros + em branco não pode exceder a quantidade total.
     */
    public function registerAttempt(int $userId, array $data): array
    {
        $total   = (int) ($data['questions_total'] ?? 0);
        $correct = (int) ($data['questions_correct'] ?? 0);
        $wrong   = (int) ($data['questions_wrong'] ?? 0);
        $blank   = (int) ($data['questions_blank'] ?? 0);

        if ($total <= 0) {
            throw new RuntimeException('Informe a quantidade total de questões.');
        }
        if (min($correct, $wrong, $blank) < 0) {
            throw new RuntimeException('Os valores não podem ser negativos.');
        }
        if ($correct + $wrong + $blank > $total) {
            throw new RuntimeException('Acertos + erros + em branco não pode ser maior que o total de questões.');
        }

        $model = new StudyQuestionAttemptModel();
        $id    = $model->insert([
            'user_id'           => $userId,
            'subject_id'        => (int) $data['subject_id'],
            'topic_id'          => ! empty($data['topic_id']) ? (int) $data['topic_id'] : null,
            'resource_id'       => ! empty($data['resource_id']) ? (int) $data['resource_id'] : null,
            'attempt_date'      => $data['attempt_date'] ?? date('Y-m-d'),
            'source'            => $data['source'] ?? null,
            'questions_total'   => $total,
            'questions_correct' => $correct,
            'questions_wrong'   => $wrong,
            'questions_blank'   => $blank,
            'duration_minutes'  => (int) ($data['duration_minutes'] ?? 0),
            'score_percentage'  => round($correct / $total * 100, 2),
            'error_notes'       => $data['error_notes'] ?? null,
        ]);

        if (! $id) {
            throw new RuntimeException(implode(' ', $model->errors() ?: ['Não foi possível salvar o registro.']));
        }

        /** @var StudyProgressService $progress */
        $progress = service('studyProgress');
        $result   = $progress->registerQuestions($userId, $total, $correct, $data['attempt_date'] ?? null);
        $badges   = $progress->checkBadges($userId);

        return [
            'attempt'    => $model->find($id),
            'xp_awarded' => $result['xp_awarded'],
            'new_badges' => $badges,
        ];
    }

    public function updateAttempt(int $userId, int $attemptId, array $data): array
    {
        $model   = new StudyQuestionAttemptModel();
        $attempt = $model->where('user_id', $userId)->find($attemptId);

        if ($attempt === null) {
            throw new RuntimeException('Registro não encontrado.');
        }

        $total   = (int) ($data['questions_total'] ?? $attempt['questions_total']);
        $correct = (int) ($data['questions_correct'] ?? $attempt['questions_correct']);
        $wrong   = (int) ($data['questions_wrong'] ?? $attempt['questions_wrong']);
        $blank   = (int) ($data['questions_blank'] ?? $attempt['questions_blank']);

        if ($total <= 0 || $correct + $wrong + $blank > $total) {
            throw new RuntimeException('Acertos + erros + em branco não pode ser maior que o total de questões.');
        }

        $allowed = array_intersect_key($data, array_flip([
            'subject_id', 'topic_id', 'resource_id', 'attempt_date', 'source',
            'questions_total', 'questions_correct', 'questions_wrong', 'questions_blank',
            'duration_minutes', 'error_notes',
        ]));

        $allowed['score_percentage'] = round($correct / $total * 100, 2);

        $model->update($attemptId, $allowed);

        return $model->find($attemptId);
    }

    public function deleteAttempt(int $userId, int $attemptId): void
    {
        $model   = new StudyQuestionAttemptModel();
        $attempt = $model->where('user_id', $userId)->find($attemptId);

        if ($attempt === null) {
            throw new RuntimeException('Registro não encontrado.');
        }

        $model->delete($attemptId);
    }

    public function listAttempts(int $userId, array $filters = [], int $limit = 50): array
    {
        $db      = db_connect();
        $builder = $db->table('study_question_attempts a')
            ->select('a.*, s.name AS subject_name, s.color AS subject_color, t.name AS topic_name')
            ->join('study_subjects s', 's.id = a.subject_id')
            ->join('study_topics t', 't.id = a.topic_id', 'left')
            ->where('a.user_id', $userId)
            ->where('a.deleted_at', null);

        if (! empty($filters['subject_id'])) {
            $builder->where('a.subject_id', (int) $filters['subject_id']);
        }
        $this->applyDateFilter($builder, 'a.attempt_date', $filters);

        return $builder->orderBy('a.attempt_date', 'DESC')->orderBy('a.id', 'DESC')->limit($limit)->get()->getResultArray();
    }

    // ------------------------------------------------------------------
    // Dados para gráficos
    // ------------------------------------------------------------------

    public function minutesPerWeek(int $userId, int $weeks = 12): array
    {
        $rows = db_connect()->table('study_daily_progress')
            ->select('progress_date, studied_minutes')
            ->where('user_id', $userId)
            ->where('progress_date >=', date('Y-m-d', strtotime('-' . ($weeks * 7) . ' days')))
            ->orderBy('progress_date', 'ASC')
            ->get()
            ->getResultArray();

        $byWeek = [];
        foreach ($rows as $row) {
            $ts    = strtotime($row['progress_date']);
            $label = date('d/m', strtotime('monday this week', $ts));
            $byWeek[$label] = ($byWeek[$label] ?? 0) + (int) $row['studied_minutes'];
        }

        return ['labels' => array_keys($byWeek), 'values' => array_values($byWeek)];
    }

    public function accuracyBySubject(int $userId, array $filters = []): array
    {
        $builder = db_connect()->table('study_question_attempts a')
            ->select('s.name, s.color, COALESCE(SUM(a.questions_total), 0) AS total, COALESCE(SUM(a.questions_correct), 0) AS correct', false)
            ->join('study_subjects s', 's.id = a.subject_id')
            ->where('a.user_id', $userId)
            ->where('a.deleted_at', null)
            ->groupBy('s.id, s.name, s.color');
        $this->applyDateFilter($builder, 'a.attempt_date', $filters);

        $rows = $builder->get()->getResultArray();

        $labels = [];
        $values = [];
        $colors = [];

        foreach ($rows as $row) {
            if ((int) $row['total'] === 0) {
                continue;
            }
            $labels[] = $row['name'];
            $values[] = round((int) $row['correct'] / (int) $row['total'] * 100, 1);
            $colors[] = $row['color'] ?: '#1B7A5E';
        }

        return ['labels' => $labels, 'values' => $values, 'colors' => $colors];
    }

    public function accuracyEvolution(int $userId, int $weeks = 12): array
    {
        $rows = db_connect()->table('study_question_attempts')
            ->select('attempt_date, questions_total, questions_correct')
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->where('attempt_date >=', date('Y-m-d', strtotime('-' . ($weeks * 7) . ' days')))
            ->orderBy('attempt_date', 'ASC')
            ->get()
            ->getResultArray();

        $byWeek = [];
        foreach ($rows as $row) {
            $label = date('d/m', strtotime('monday this week', strtotime($row['attempt_date'])));
            $byWeek[$label]['total']   = ($byWeek[$label]['total'] ?? 0) + (int) $row['questions_total'];
            $byWeek[$label]['correct'] = ($byWeek[$label]['correct'] ?? 0) + (int) $row['questions_correct'];
        }

        $labels = [];
        $values = [];
        foreach ($byWeek as $label => $data) {
            $labels[] = $label;
            $values[] = $data['total'] > 0 ? round($data['correct'] / $data['total'] * 100, 1) : 0;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    public function timeDistribution(int $userId): array
    {
        $rows = db_connect()->table('study_sessions ss')
            ->select('s.name, s.color, COALESCE(SUM(ss.duration_seconds), 0) AS seconds', false)
            ->join('study_subjects s', 's.id = ss.subject_id')
            ->where('ss.user_id', $userId)
            ->where('ss.status', 'completed')
            ->where('ss.deleted_at', null)
            ->groupBy('s.id, s.name, s.color')
            ->orderBy('seconds', 'DESC')
            ->get()
            ->getResultArray();

        return [
            'labels' => array_column($rows, 'name'),
            'values' => array_map(static fn (array $row): float => round((int) $row['seconds'] / 60), $rows),
            'colors' => array_map(static fn (array $row): string => $row['color'] ?: '#1B7A5E', $rows),
        ];
    }

    public function tasksCompletedPerWeek(int $userId, int $weeks = 12): array
    {
        $rows = db_connect()->table('study_tasks')
            ->select('completed_at')
            ->where('user_id', $userId)
            ->where('status', 'done')
            ->where('completed_at IS NOT NULL')
            ->where('deleted_at', null)
            ->where('completed_at >=', date('Y-m-d', strtotime('-' . ($weeks * 7) . ' days')))
            ->get()
            ->getResultArray();

        $byWeek = [];
        foreach ($rows as $row) {
            $label = date('d/m', strtotime('monday this week', strtotime($row['completed_at'])));
            $byWeek[$label] = ($byWeek[$label] ?? 0) + 1;
        }
        ksort($byWeek);

        return ['labels' => array_keys($byWeek), 'values' => array_values($byWeek)];
    }

    public function plannedVsDone(int $userId, int $weeks = 12): array
    {
        $rows = db_connect()->table('study_daily_progress')
            ->select('progress_date, planned_minutes, studied_minutes')
            ->where('user_id', $userId)
            ->where('progress_date >=', date('Y-m-d', strtotime('-' . ($weeks * 7) . ' days')))
            ->orderBy('progress_date', 'ASC')
            ->get()
            ->getResultArray();

        $byWeek = [];
        foreach ($rows as $row) {
            $label = date('d/m', strtotime('monday this week', strtotime($row['progress_date'])));
            $byWeek[$label]['planned'] = ($byWeek[$label]['planned'] ?? 0) + (int) $row['planned_minutes'];
            $byWeek[$label]['studied'] = ($byWeek[$label]['studied'] ?? 0) + (int) $row['studied_minutes'];
        }

        return [
            'labels'  => array_keys($byWeek),
            'planned' => array_column($byWeek, 'planned'),
            'studied' => array_column($byWeek, 'studied'),
        ];
    }

    // ------------------------------------------------------------------

    private function subjectExtreme(int $userId, array $filters, bool $best): ?array
    {
        $builder = db_connect()->table('study_question_attempts a')
            ->select('s.name, COALESCE(SUM(a.questions_total), 0) AS total, COALESCE(SUM(a.questions_correct), 0) AS correct', false)
            ->join('study_subjects s', 's.id = a.subject_id')
            ->where('a.user_id', $userId)
            ->where('a.deleted_at', null)
            ->groupBy('s.id, s.name')
            ->having('total >', 0);
        $this->applyDateFilter($builder, 'a.attempt_date', $filters);

        $rows = $builder->get()->getResultArray();

        if ($rows === []) {
            return null;
        }

        usort($rows, static function (array $a, array $b): int {
            $accA = (int) $a['correct'] / max(1, (int) $a['total']);
            $accB = (int) $b['correct'] / max(1, (int) $b['total']);

            return $accA <=> $accB;
        });

        $row = $best ? end($rows) : $rows[0];

        return [
            'name'     => $row['name'],
            'accuracy' => round((int) $row['correct'] / max(1, (int) $row['total']) * 100, 1),
            'total'    => (int) $row['total'],
        ];
    }

    private function topContents(int $userId, array $filters, int $limit = 5): array
    {
        $builder = db_connect()->table('study_sessions ss')
            ->select('t.name, COALESCE(SUM(ss.duration_seconds), 0) AS seconds', false)
            ->join('study_topics t', 't.id = ss.topic_id')
            ->where('ss.user_id', $userId)
            ->where('ss.status', 'completed')
            ->where('ss.deleted_at', null)
            ->groupBy('t.id, t.name')
            ->orderBy('seconds', 'DESC')
            ->limit($limit);
        $this->applyDateFilter($builder, 'ss.started_at', $filters, true);

        return $builder->get()->getResultArray();
    }

    private function errorContents(int $userId, array $filters, int $limit = 5): array
    {
        $builder = db_connect()->table('study_question_attempts a')
            ->select('COALESCE(t.name, s.name) AS name, COALESCE(SUM(a.questions_wrong), 0) AS wrong', false)
            ->join('study_subjects s', 's.id = a.subject_id')
            ->join('study_topics t', 't.id = a.topic_id', 'left')
            ->where('a.user_id', $userId)
            ->where('a.deleted_at', null)
            ->groupBy('t.id, t.name, s.name')
            ->having('wrong >', 0)
            ->orderBy('wrong', 'DESC')
            ->limit($limit);
        $this->applyDateFilter($builder, 'a.attempt_date', $filters);

        return $builder->get()->getResultArray();
    }

    private function applyDateFilter(object $builder, string $field, array $filters, bool $isDatetime = false): void
    {
        if (! empty($filters['date_from'])) {
            $builder->where($field . ' >=', $filters['date_from'] . ($isDatetime ? ' 00:00:00' : ''));
        }
        if (! empty($filters['date_to'])) {
            $builder->where($field . ' <=', $filters['date_to'] . ($isDatetime ? ' 23:59:59' : ''));
        }
    }
}
