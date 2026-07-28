<?php

declare(strict_types=1);

namespace App\Services\Study;

use App\Models\StudyPlanModel;
use App\Models\StudyPlanWeekModel;

/**
 * Leitura do plano de estudos e do cronograma semanal.
 */
class StudyPlanService
{
    public function getActivePlan(int $userId): ?array
    {
        return (new StudyPlanModel())
            ->where('user_id', $userId)
            ->where('active', 1)
            ->orderBy('id', 'DESC')
            ->first();
    }

    /**
     * Semana do plano que contém a data (null se fora do período).
     */
    public function getWeekOf(int $userId, ?string $date = null): ?array
    {
        $date = $date ?? date('Y-m-d');
        $plan = $this->getActivePlan($userId);

        if ($plan === null) {
            return null;
        }

        return (new StudyPlanWeekModel())
            ->where('plan_id', $plan['id'])
            ->where('start_date <=', $date)
            ->where('end_date >=', $date)
            ->first();
    }

    /**
     * Cronograma completo: semanas com suas tarefas (e progresso do checklist).
     */
    public function getSchedule(int $userId): array
    {
        $plan = $this->getActivePlan($userId);

        if ($plan === null) {
            return ['plan' => null, 'weeks' => []];
        }

        $weeks = (new StudyPlanWeekModel())
            ->where('plan_id', $plan['id'])
            ->orderBy('week_number', 'ASC')
            ->findAll();

        $db    = db_connect();
        $tasks = $db->table('study_tasks t')
            ->select('t.*, s.name AS subject_name, s.color AS subject_color,
                      (SELECT COUNT(*) FROM study_task_checklists c WHERE c.task_id = t.id) AS checklist_total,
                      (SELECT COUNT(*) FROM study_task_checklists c WHERE c.task_id = t.id AND c.is_completed = 1) AS checklist_done')
            ->join('study_subjects s', 's.id = t.subject_id')
            ->where('t.user_id', $userId)
            ->where('t.plan_id', $plan['id'])
            ->where('t.deleted_at', null)
            ->orderBy('t.scheduled_date', 'ASC')
            ->get()
            ->getResultArray();

        $byWeek = [];
        foreach ($tasks as $task) {
            $byWeek[(int) $task['plan_week_id']][] = $task;
        }

        $today = date('Y-m-d');

        foreach ($weeks as &$week) {
            $week['tasks']      = $byWeek[(int) $week['id']] ?? [];
            $week['total']      = count($week['tasks']);
            $week['done']       = count(array_filter($week['tasks'], static fn (array $t): bool => $t['status'] === 'done'));
            $week['percent']    = $week['total'] > 0 ? (int) round($week['done'] / $week['total'] * 100) : 0;
            $week['is_current'] = $week['start_date'] <= $today && $week['end_date'] >= $today;
        }

        return ['plan' => $plan, 'weeks' => $weeks];
    }

    /**
     * Percentual de cumprimento do cronograma até hoje (tarefas vencidas concluídas).
     */
    public function getAdherence(int $userId): array
    {
        $plan = $this->getActivePlan($userId);

        if ($plan === null) {
            return ['due' => 0, 'done' => 0, 'percent' => 0];
        }

        $db    = db_connect();
        $today = date('Y-m-d');

        $row = $db->table('study_tasks')
            ->select("COUNT(*) AS due, SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) AS done", false)
            ->where('user_id', $userId)
            ->where('plan_id', $plan['id'])
            ->where('scheduled_date <=', $today)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        $due  = (int) ($row['due'] ?? 0);
        $done = (int) ($row['done'] ?? 0);

        return [
            'due'     => $due,
            'done'    => $done,
            'percent' => $due > 0 ? (int) round($done / $due * 100) : 0,
        ];
    }
}
