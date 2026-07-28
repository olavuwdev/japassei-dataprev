<?php

declare(strict_types=1);

namespace App\Controllers\Estudos;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * API JSON do timer / sessões de estudo.
 * A autorização por usuário é garantida nos services (filtro por user_id).
 */
class SessaoController extends BaseController
{
    /**
     * GET estudos/api/sessao/ativa
     */
    public function active(): ResponseInterface
    {
        $session = service('studySession')->getActive($this->userId());

        return $this->jsonResponse(true, ['session' => $session]);
    }

    /**
     * POST estudos/api/sessao/iniciar
     * Body JSON: task_id (opcional), subject_id, topic_id, session_type, planned_minutes.
     */
    public function start(): ResponseInterface
    {
        $data = (array) ($this->request->getJSON(true) ?? []);

        try {
            $session = service('studySession')->start($this->userId(), $data);

            return $this->jsonResponse(true, ['session' => $session], 'Sessão iniciada. Bons estudos!');
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }

    /**
     * POST estudos/api/sessao/(id)/pausar
     */
    public function pause($id): ResponseInterface
    {
        try {
            $session = service('studySession')->pause($this->userId(), (int) $id);

            return $this->jsonResponse(true, ['session' => $session], 'Sessão pausada.');
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }

    /**
     * POST estudos/api/sessao/(id)/retomar
     */
    public function resume($id): ResponseInterface
    {
        try {
            $session = service('studySession')->resume($this->userId(), (int) $id);

            return $this->jsonResponse(true, ['session' => $session], 'Sessão retomada.');
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }

    /**
     * POST estudos/api/sessao/(id)/concluir
     * Body JSON: notes (opcional). Retorna resumo completo (duração, XP,
     * meta, ofensiva, conquistas).
     */
    public function finish($id): ResponseInterface
    {
        $data = (array) ($this->request->getJSON(true) ?? []);

        try {
            $result = service('studySession')->finish($this->userId(), (int) $id, [
                'notes' => $data['notes'] ?? null,
            ]);

            return $this->jsonResponse(true, $result, 'Sessão concluída!');
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }

    /**
     * POST estudos/api/sessao/(id)/cancelar
     */
    public function cancel($id): ResponseInterface
    {
        try {
            $session = service('studySession')->cancel($this->userId(), (int) $id);

            return $this->jsonResponse(true, ['session' => $session], 'Sessão cancelada.');
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }
}
