<?php

declare(strict_types=1);

namespace App\Services\Study;

use App\Models\StudyKanbanColumnModel;
use App\Models\StudyTaskModel;
use App\Models\StudyTaskStatusHistoryModel;
use RuntimeException;

/**
 * Quadro Kanban: leitura do board e movimentação transacional de cards,
 * com reindexação de posições (nunca duplicadas dentro da coluna).
 */
class StudyKanbanService
{
    /**
     * Board completo do usuário, com filtros opcionais:
     * subject_id, week_id, task_type, priority, status.
     */
    public function getBoard(int $userId, array $filters = []): array
    {
        $columns = (new StudyKanbanColumnModel())
            ->where('active', 1)
            ->orderBy('position', 'ASC')
            ->findAll();

        $db      = db_connect();
        $builder = $db->table('study_tasks t')
            ->select('t.*, s.name AS subject_name, s.color AS subject_color, w.week_number,
                      (SELECT COUNT(*) FROM study_task_checklists c WHERE c.task_id = t.id) AS checklist_total,
                      (SELECT COUNT(*) FROM study_task_checklists c WHERE c.task_id = t.id AND c.is_completed = 1) AS checklist_done,
                      (SELECT COUNT(*) FROM study_reviews r WHERE r.origin_task_id = t.id AND r.status IN (\'available\', \'overdue\') AND r.deleted_at IS NULL) AS pending_reviews')
            ->join('study_subjects s', 's.id = t.subject_id')
            ->join('study_plan_weeks w', 'w.id = t.plan_week_id', 'left')
            ->where('t.user_id', $userId)
            ->where('t.deleted_at', null);

        if (! empty($filters['subject_id'])) {
            $builder->where('t.subject_id', (int) $filters['subject_id']);
        }
        if (! empty($filters['week_id'])) {
            $builder->where('t.plan_week_id', (int) $filters['week_id']);
        }
        if (! empty($filters['task_type'])) {
            $builder->where('t.task_type', $filters['task_type']);
        }
        if (! empty($filters['priority'])) {
            $builder->where('t.priority', (int) $filters['priority']);
        }
        if (! empty($filters['status'])) {
            $builder->where('t.status', $filters['status']);
        }

        $tasks = $builder->orderBy('t.position', 'ASC')->get()->getResultArray();

        $today    = date('Y-m-d');
        $byColumn = [];

        foreach ($tasks as $task) {
            $task['is_late'] = $task['status'] !== 'done'
                && $task['scheduled_date'] !== null
                && $task['scheduled_date'] < $today;
            $byColumn[(int) $task['kanban_column_id']][] = $task;
        }

        foreach ($columns as &$column) {
            $column['cards'] = $byColumn[(int) $column['id']] ?? [];
        }

        return $columns;
    }

    /**
     * Move um card para outra coluna/posição em transação:
     * altera a coluna, reindexa posições e registra histórico.
     */
    public function moveCard(int $userId, int $taskId, int $toColumnId, int $position): array
    {
        $task = (new StudyTaskModel())->where('user_id', $userId)->find($taskId);
        if ($task === null) {
            throw new RuntimeException('Tarefa não encontrada.');
        }

        $toColumn = (new StudyKanbanColumnModel())->where('active', 1)->find($toColumnId);
        if ($toColumn === null) {
            throw new RuntimeException('Coluna não encontrada.');
        }

        $fromColumnId = (int) $task['kanban_column_id'];
        $position     = max(1, $position);

        $db = db_connect();
        $db->transException(true)->transStart();

        // Retira o card da coluna de origem e fecha o "buraco".
        $db->table('study_tasks')
            ->where('user_id', $userId)
            ->where('kanban_column_id', $fromColumnId)
            ->where('position >', (int) $task['position'])
            ->where('deleted_at', null)
            ->set('position', 'position - 1', false)
            ->update();

        // Abre espaço na coluna de destino.
        $db->table('study_tasks')
            ->where('user_id', $userId)
            ->where('kanban_column_id', $toColumnId)
            ->where('position >=', $position)
            ->where('id !=', $taskId)
            ->where('deleted_at', null)
            ->set('position', 'position + 1', false)
            ->update();

        $newStatus = $this->statusForColumn($toColumn['code'], $task['status']);

        $update = [
            'kanban_column_id' => $toColumnId,
            'position'         => $position,
            'status'           => $newStatus,
        ];

        $completedNow = false;
        if ((bool) $toColumn['is_completed_column'] && $task['status'] !== 'done') {
            $update['completed_at'] = date('Y-m-d H:i:s');
            $completedNow           = true;
        }
        if (! (bool) $toColumn['is_completed_column'] && $task['status'] === 'done') {
            $update['completed_at'] = null;
        }

        (new StudyTaskModel())->update($taskId, $update);

        (new StudyTaskStatusHistoryModel())->insert([
            'task_id'        => $taskId,
            'user_id'        => $userId,
            'from_column_id' => $fromColumnId,
            'to_column_id'   => $toColumnId,
            'from_status'    => $task['status'],
            'to_status'      => $newStatus,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        $db->transComplete();

        $result = [
            'task'         => (new StudyTaskModel())->find($taskId),
            'completed'    => $completedNow,
            'goal_met_now' => false,
            'xp_awarded'   => 0,
            'new_badges'   => [],
        ];

        // Efeitos de conclusão (progresso, revisões, ofensiva) fora da transação do board.
        if ($completedNow) {
            /** @var StudyProgressService $progress */
            $progress       = service('studyProgress');
            $progressResult = $progress->registerTaskCompleted($userId);

            $freshTask = (new StudyTaskModel())->find($taskId);
            service('studyReview')->generateForTask($userId, $freshTask);

            $result['goal_met_now'] = $progressResult['goal_met_now'];
            $result['xp_awarded']   = $progressResult['xp_awarded'];
            $result['new_badges']   = $progress->checkBadges($userId);
            $result['task']         = $freshTask;
        }

        return $result;
    }

    /**
     * Filtros disponíveis para o board (disciplinas, semanas, tipos, prioridades).
     */
    public function getFilterOptions(int $userId): array
    {
        $db = db_connect();

        $subjects = $db->table('study_subjects s')
            ->select('DISTINCT s.id, s.name', false)
            ->join('study_tasks t', 't.subject_id = s.id')
            ->where('t.user_id', $userId)
            ->where('t.deleted_at', null)
            ->orderBy('s.name', 'ASC')
            ->get()
            ->getResultArray();

        $weeks = $db->table('study_plan_weeks w')
            ->select('DISTINCT w.id, w.week_number, w.title', false)
            ->join('study_tasks t', 't.plan_week_id = w.id')
            ->where('t.user_id', $userId)
            ->orderBy('w.week_number', 'ASC')
            ->get()
            ->getResultArray();

        return [
            'subjects'   => $subjects,
            'weeks'      => $weeks,
            'task_types' => [
                'theory'    => 'Teoria',
                'questions' => 'Questões',
                'review'    => 'Revisão',
                'practice'  => 'Prática',
                'mock_exam' => 'Simulado',
            ],
            'priorities' => [1 => 'Alta', 2 => 'Média-alta', 3 => 'Média', 4 => 'Média-baixa', 5 => 'Baixa'],
            'statuses'   => ['pending' => 'Pendente', 'in_progress' => 'Em andamento', 'done' => 'Concluída'],
        ];
    }

    private function statusForColumn(string $columnCode, string $currentStatus): string
    {
        return match ($columnCode) {
            'done'        => 'done',
            'in_progress' => 'in_progress',
            'review'      => 'in_progress',
            default       => $currentStatus === 'done' ? 'pending' : $currentStatus,
        };
    }
}
