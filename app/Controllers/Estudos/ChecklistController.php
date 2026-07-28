<?php

declare(strict_types=1);

namespace App\Controllers\Estudos;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * API JSON dos itens de checklist das tarefas.
 */
class ChecklistController extends BaseController
{
    /**
     * POST estudos/api/checklist/(id)/alternar — marca/desmarca item.
     * Retorna item, progresso e sugestão de conclusão da tarefa.
     */
    public function toggle($id): ResponseInterface
    {
        try {
            $result = service('studyTask')->toggleChecklistItem($this->userId(), (int) $id);

            return $this->jsonResponse(true, $result);
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }

    /**
     * POST estudos/api/checklist — Body JSON: task_id, title,
     * estimated_minutes (opcional), is_required (opcional).
     */
    public function create(): ResponseInterface
    {
        $data   = (array) ($this->request->getJSON(true) ?? []);
        $taskId = (int) ($data['task_id'] ?? 0);
        $title  = trim((string) ($data['title'] ?? ''));

        if ($taskId <= 0 || $title === '') {
            return $this->jsonResponse(false, [], 'Informe a tarefa e o título do item.', 422);
        }

        try {
            $item = service('studyTask')->addChecklistItem(
                $this->userId(),
                $taskId,
                $title,
                max(0, (int) ($data['estimated_minutes'] ?? 0)),
                ! empty($data['is_required'])
            );

            return $this->jsonResponse(true, ['item' => $item], 'Item adicionado.');
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }

    /**
     * POST estudos/api/checklist/(id)/editar — Body JSON: title,
     * estimated_minutes, is_required.
     */
    public function update($id): ResponseInterface
    {
        $data    = (array) ($this->request->getJSON(true) ?? []);
        $allowed = [];

        if (isset($data['title'])) {
            $title = trim((string) $data['title']);
            if ($title === '') {
                return $this->jsonResponse(false, [], 'O título não pode ficar vazio.', 422);
            }
            $allowed['title'] = $title;
        }

        if (isset($data['estimated_minutes'])) {
            $allowed['estimated_minutes'] = max(0, (int) $data['estimated_minutes']);
        }

        if (array_key_exists('is_required', $data)) {
            $allowed['is_required'] = ! empty($data['is_required']) ? 1 : 0;
        }

        try {
            $item = service('studyTask')->updateChecklistItem($this->userId(), (int) $id, $allowed);

            return $this->jsonResponse(true, ['item' => $item], 'Item atualizado.');
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }

    /**
     * POST estudos/api/checklist/(id)/excluir
     */
    public function delete($id): ResponseInterface
    {
        try {
            service('studyTask')->deleteChecklistItem($this->userId(), (int) $id);

            return $this->jsonResponse(true, [], 'Item excluído.');
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }

    /**
     * POST estudos/api/checklist/reordenar — Body JSON: task_id, ordered_ids[].
     */
    public function reorder(): ResponseInterface
    {
        $data       = (array) ($this->request->getJSON(true) ?? []);
        $taskId     = (int) ($data['task_id'] ?? 0);
        $orderedIds = $data['ordered_ids'] ?? [];

        if ($taskId <= 0 || ! is_array($orderedIds) || $orderedIds === []) {
            return $this->jsonResponse(false, [], 'Informe a tarefa e a ordem dos itens.', 422);
        }

        try {
            service('studyTask')->reorderChecklist($this->userId(), $taskId, array_map('intval', $orderedIds));

            return $this->jsonResponse(true, [], 'Checklist reordenado.');
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }
}
