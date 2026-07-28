<?php

declare(strict_types=1);

namespace App\Controllers\Estudos;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * API JSON de tarefas de estudo.
 */
class TarefasController extends BaseController
{
    /**
     * GET estudos/api/tarefas/(id) — tarefa com checklist, disciplina e tópico.
     */
    public function show($id): ResponseInterface
    {
        try {
            $task = service('studyTask')->getWithChecklist($this->userId(), (int) $id);

            return $this->jsonResponse(true, ['task' => $task]);
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }

    /**
     * POST estudos/api/tarefas/(id)/concluir — retorna XP, ofensiva,
     * revisões geradas e conquistas novas.
     */
    public function complete($id): ResponseInterface
    {
        try {
            $result = service('studyTask')->complete($this->userId(), (int) $id);

            return $this->jsonResponse(true, $result, 'Tarefa concluída!');
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }

    /**
     * POST estudos/api/tarefas/(id)/reagendar — Body JSON: new_date|date|scheduled_date (Y-m-d).
     * Se bring_to_today=true, reagenda para hoje e move para a coluna "Hoje".
     */
    public function reschedule($id): ResponseInterface
    {
        $data = (array) ($this->request->getJSON(true) ?? []);

        try {
            if (! empty($data['bring_to_today'])) {
                $task = service('studyTask')->bringToToday($this->userId(), (int) $id);

                return $this->jsonResponse(true, ['task' => $task], 'Tarefa trazida para hoje.');
            }

            $newDate = trim((string) ($data['new_date'] ?? $data['date'] ?? $data['scheduled_date'] ?? ''));

            if ($newDate === '') {
                return $this->jsonResponse(false, [], 'Informe a nova data.', 422);
            }

            $task = service('studyTask')->reschedule($this->userId(), (int) $id, $newDate);

            return $this->jsonResponse(true, ['task' => $task], 'Tarefa reagendada.');
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }

    /**
     * POST estudos/api/tarefas/(id)/observacao — Body JSON: note.
     */
    public function addNote($id): ResponseInterface
    {
        $data = (array) ($this->request->getJSON(true) ?? []);
        $note = trim((string) ($data['note'] ?? ''));

        if ($note === '') {
            return $this->jsonResponse(false, [], 'Informe a observação.', 422);
        }

        try {
            $task = service('studyTask')->addNote($this->userId(), (int) $id, $note);

            return $this->jsonResponse(true, ['task' => $task], 'Observação registrada.');
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }
}
