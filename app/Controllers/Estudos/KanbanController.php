<?php

declare(strict_types=1);

namespace App\Controllers\Estudos;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * Quadro Kanban de estudos: página, leitura do board e movimentação de cards.
 */
class KanbanController extends BaseController
{
    public function index(): string
    {
        $userId = $this->userId();
        $kanban = service('studyKanban');

        return view('estudos/kanban', [
            'columns'       => $kanban->getBoard($userId),
            'filterOptions' => $kanban->getFilterOptions($userId),
        ]);
    }

    /**
     * GET /estudos/api/kanban/board — board filtrado via query string.
     */
    public function board(): ResponseInterface
    {
        $filters = array_filter([
            'subject_id' => $this->request->getGet('subject_id'),
            'week_id'    => $this->request->getGet('week_id'),
            'task_type'  => $this->request->getGet('task_type'),
            'priority'   => $this->request->getGet('priority'),
            'status'     => $this->request->getGet('status'),
        ], static fn ($value): bool => $value !== null && $value !== '');

        $columns = service('studyKanban')->getBoard($this->userId(), $filters);

        return $this->jsonResponse(true, ['columns' => $columns]);
    }

    /**
     * POST /estudos/api/kanban/mover — body JSON {task_id, to_column_id, position}.
     */
    public function move(): ResponseInterface
    {
        $payload = $this->request->getJSON(true) ?? [];

        $taskId     = (int) ($payload['task_id'] ?? 0);
        $toColumnId = (int) ($payload['to_column_id'] ?? 0);
        $position   = (int) ($payload['position'] ?? 1);

        try {
            $result = service('studyKanban')->moveCard($this->userId(), $taskId, $toColumnId, $position);
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }

        return $this->jsonResponse(true, [
            'task'         => $result['task'],
            'completed'    => $result['completed'],
            'goal_met_now' => $result['goal_met_now'],
            'xp_awarded'   => $result['xp_awarded'],
            'new_badges'   => $result['new_badges'],
        ], 'Tarefa movida com sucesso.');
    }
}
