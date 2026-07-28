<?php

declare(strict_types=1);

namespace App\Services\Study;

use App\Models\StudyKanbanColumnModel;
use App\Models\StudyTaskChecklistModel;
use App\Models\StudyTaskModel;
use App\Models\StudyTaskStatusHistoryModel;
use App\Models\StudyUserSettingModel;
use RuntimeException;

/**
 * Tarefas de estudo e seus checklists.
 */
class StudyTaskService
{
    /**
     * Tarefa principal do dia (obrigatória, maior prioridade) com checklist.
     */
    public function getMainTask(int $userId, ?string $date = null): ?array
    {
        $date = $date ?? date('Y-m-d');

        $task = (new StudyTaskModel())
            ->where('user_id', $userId)
            ->where('scheduled_date', $date)
            ->where('is_required', 1)
            ->orderBy('priority', 'ASC')
            ->orderBy('id', 'ASC')
            ->first();

        return $task !== null ? $this->hydrate($task) : null;
    }

    /**
     * Todas as tarefas do dia, com checklist e disciplina.
     *
     * @return list<array>
     */
    public function getTasksForDate(int $userId, ?string $date = null): array
    {
        $date = $date ?? date('Y-m-d');

        $tasks = (new StudyTaskModel())
            ->where('user_id', $userId)
            ->where('scheduled_date', $date)
            ->orderBy('priority', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();

        return array_map(fn (array $task): array => $this->hydrate($task), $tasks);
    }

    public function getWithChecklist(int $userId, int $taskId): array
    {
        return $this->hydrate($this->findOwned($userId, $taskId));
    }

    /**
     * Próxima tarefa pendente a partir de hoje (inclusive futuras).
     */
    public function getNextUpcomingTask(int $userId, ?string $fromDate = null): ?array
    {
        $fromDate = $fromDate ?? date('Y-m-d');

        $task = (new StudyTaskModel())
            ->where('user_id', $userId)
            ->where('scheduled_date >=', $fromDate)
            ->where('status !=', 'done')
            ->orderBy('scheduled_date', 'ASC')
            ->orderBy('priority', 'ASC')
            ->orderBy('id', 'ASC')
            ->first();

        return $task !== null ? $this->hydrate($task) : null;
    }

    /**
     * Traz a tarefa para hoje: reagenda e move para a coluna "Hoje" do Kanban.
     */
    public function bringToToday(int $userId, int $taskId): array
    {
        $task  = $this->findOwned($userId, $taskId);
        $today = date('Y-m-d');

        if ($task['status'] === 'done') {
            throw new RuntimeException('Tarefas concluídas não podem ser trazidas para hoje.');
        }

        $todayColumn = (new StudyKanbanColumnModel())->where('code', 'today')->first();

        $update = ['scheduled_date' => $today];

        if ($todayColumn !== null && (int) $task['kanban_column_id'] !== (int) $todayColumn['id']) {
            $update['kanban_column_id'] = (int) $todayColumn['id'];
            $update['position']         = $this->nextPosition((int) $todayColumn['id']);
        }

        (new StudyTaskModel())->update($taskId, $update);

        return $this->getWithChecklist($userId, $taskId);
    }

    /**
     * Conclui a tarefa: status, coluna "done" do Kanban, histórico, progresso,
     * revisões e reavaliação de meta/ofensiva — em transação.
     */
    public function complete(int $userId, int $taskId): array
    {
        $task = $this->findOwned($userId, $taskId);

        if ($task['status'] === 'done') {
            throw new RuntimeException('Esta tarefa já foi concluída.');
        }

        $db = db_connect();
        $db->transException(true)->transStart();

        $doneColumn = (new StudyKanbanColumnModel())->where('code', 'done')->first();
        $now        = date('Y-m-d H:i:s');

        (new StudyTaskModel())->update($taskId, [
            'status'           => 'done',
            'completed_at'     => $now,
            'kanban_column_id' => $doneColumn !== null ? $doneColumn['id'] : $task['kanban_column_id'],
            'position'         => $this->nextPosition($doneColumn !== null ? (int) $doneColumn['id'] : (int) $task['kanban_column_id']),
        ]);

        (new StudyTaskStatusHistoryModel())->insert([
            'task_id'        => $taskId,
            'user_id'        => $userId,
            'from_column_id' => $task['kanban_column_id'],
            'to_column_id'   => $doneColumn !== null ? $doneColumn['id'] : $task['kanban_column_id'],
            'from_status'    => $task['status'],
            'to_status'      => 'done',
            'created_at'     => $now,
        ]);

        /** @var StudyProgressService $progress */
        $progress = service('studyProgress');
        $result   = $progress->registerTaskCompleted($userId);

        /** @var StudyReviewService $reviews */
        $reviews  = service('studyReview');
        $freshTask = (new StudyTaskModel())->find($taskId);
        $created   = $reviews->generateForTask($userId, $freshTask);

        $db->transComplete();

        $badges = $progress->checkBadges($userId);

        return [
            'task'            => $this->hydrate($freshTask),
            'reviews_created' => $created,
            'goal_met_now'    => $result['goal_met_now'],
            'xp_awarded'      => $result['xp_awarded'],
            'streak'          => service('studyStreak')->getState($userId),
            'new_badges'      => $badges,
        ];
    }

    public function reschedule(int $userId, int $taskId, string $newDate): array
    {
        $this->findOwned($userId, $taskId);

        if (strtotime($newDate) === false) {
            throw new RuntimeException('Data inválida.');
        }

        (new StudyTaskModel())->update($taskId, ['scheduled_date' => $newDate]);

        return $this->getWithChecklist($userId, $taskId);
    }

    public function addNote(int $userId, int $taskId, string $note): array
    {
        $task = $this->findOwned($userId, $taskId);

        $description = trim(($task['description'] ?? '') . "\n" . $note);
        (new StudyTaskModel())->update($taskId, ['description' => $description]);

        return $this->getWithChecklist($userId, $taskId);
    }

    // ------------------------------------------------------------------
    // Checklist
    // ------------------------------------------------------------------

    /**
     * Alterna um item do checklist. Retorna item, progresso e sugestão de
     * conclusão da tarefa quando os obrigatórios estiverem completos.
     */
    public function toggleChecklistItem(int $userId, int $itemId): array
    {
        $item = $this->findOwnedChecklistItem($userId, $itemId);

        $completed = ! (bool) $item['is_completed'];

        (new StudyTaskChecklistModel())->update($itemId, [
            'is_completed' => $completed ? 1 : 0,
            'completed_at' => $completed ? date('Y-m-d H:i:s') : null,
        ]);

        $taskId   = (int) $item['task_id'];
        $task     = (new StudyTaskModel())->find($taskId);
        $progress = $this->checklistProgress($taskId);

        $autoComplete = false;
        $suggestComplete = false;

        if ($progress['required_done'] && $task['status'] !== 'done') {
            $settings     = (new StudyUserSettingModel())->where('user_id', $userId)->first();
            $autoComplete = ! empty($settings['auto_complete_tasks']);

            if ($autoComplete) {
                $this->complete($userId, $taskId);
            } else {
                $suggestComplete = true;
            }
        }

        return [
            'item'             => (new StudyTaskChecklistModel())->find($itemId),
            'progress'         => $progress,
            'suggest_complete' => $suggestComplete,
            'auto_completed'   => $autoComplete,
            'task_id'          => $taskId,
        ];
    }

    public function addChecklistItem(int $userId, int $taskId, string $title, int $estimatedMinutes = 0, bool $required = false): array
    {
        $this->findOwned($userId, $taskId);

        $model = new StudyTaskChecklistModel();
        $max   = $model->where('task_id', $taskId)->selectMax('position')->first();

        $id = $model->insert([
            'task_id'           => $taskId,
            'title'             => $title,
            'estimated_minutes' => $estimatedMinutes,
            'position'          => (int) ($max['position'] ?? 0) + 1,
            'is_required'       => $required ? 1 : 0,
            'is_completed'      => 0,
        ]);

        return $model->find($id);
    }

    public function updateChecklistItem(int $userId, int $itemId, array $data): array
    {
        $this->findOwnedChecklistItem($userId, $itemId);

        $allowed = array_intersect_key($data, array_flip(['title', 'estimated_minutes', 'is_required']));
        if ($allowed !== []) {
            (new StudyTaskChecklistModel())->update($itemId, $allowed);
        }

        return (new StudyTaskChecklistModel())->find($itemId);
    }

    public function deleteChecklistItem(int $userId, int $itemId): void
    {
        $this->findOwnedChecklistItem($userId, $itemId);
        (new StudyTaskChecklistModel())->delete($itemId);
    }

    /**
     * @param list<int> $orderedIds
     */
    public function reorderChecklist(int $userId, int $taskId, array $orderedIds): void
    {
        $this->findOwned($userId, $taskId);
        $model = new StudyTaskChecklistModel();

        foreach ($orderedIds as $position => $itemId) {
            $item = $model->find((int) $itemId);
            if ($item !== null && (int) $item['task_id'] === $taskId) {
                $model->update((int) $itemId, ['position' => $position + 1]);
            }
        }
    }

    /**
     * @return array{total: int, done: int, percent: int, required_done: bool}
     */
    public function checklistProgress(int $taskId): array
    {
        $items = (new StudyTaskChecklistModel())->where('task_id', $taskId)->findAll();

        $total = count($items);
        $done  = count(array_filter($items, static fn (array $item): bool => (bool) $item['is_completed']));
        $requiredPending = count(array_filter(
            $items,
            static fn (array $item): bool => (bool) $item['is_required'] && ! $item['is_completed']
        ));

        return [
            'total'         => $total,
            'done'          => $done,
            'percent'       => $total > 0 ? (int) round($done / $total * 100) : 0,
            'required_done' => $total > 0 && $requiredPending === 0,
        ];
    }

    // ------------------------------------------------------------------

    private function hydrate(array $task): array
    {
        $db      = db_connect();
        $subject = $db->table('study_subjects')->where('id', $task['subject_id'])->get()->getRowArray();
        $topic   = $task['topic_id'] !== null
            ? $db->table('study_topics')->where('id', $task['topic_id'])->get()->getRowArray()
            : null;

        $task['subject']   = $subject;
        $task['topic']     = $topic;
        $task['checklist'] = (new StudyTaskChecklistModel())
            ->where('task_id', $task['id'])
            ->orderBy('position', 'ASC')
            ->findAll();
        $task['checklist_progress'] = $this->checklistProgress((int) $task['id']);

        return $task;
    }

    private function nextPosition(int $columnId): int
    {
        $max = (new StudyTaskModel())
            ->where('kanban_column_id', $columnId)
            ->selectMax('position')
            ->first();

        return (int) ($max['position'] ?? 0) + 1;
    }

    private function findOwned(int $userId, int $taskId): array
    {
        $task = (new StudyTaskModel())->where('user_id', $userId)->find($taskId);

        if ($task === null) {
            throw new RuntimeException('Tarefa não encontrada.');
        }

        return $task;
    }

    private function findOwnedChecklistItem(int $userId, int $itemId): array
    {
        $item = (new StudyTaskChecklistModel())->find($itemId);

        if ($item === null) {
            throw new RuntimeException('Item de checklist não encontrado.');
        }

        $task = (new StudyTaskModel())->where('user_id', $userId)->find((int) $item['task_id']);

        if ($task === null) {
            throw new RuntimeException('Item de checklist não encontrado.');
        }

        return $item;
    }
}
