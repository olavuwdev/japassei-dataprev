<?php

declare(strict_types=1);

namespace App\Controllers\Estudos;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * Página e API do sistema de revisões espaçadas.
 */
class RevisoesController extends BaseController
{
    public function index(): string
    {
        $grouped = service('studyReview')->getGrouped($this->userId());

        return view('estudos/revisoes', [
            'grouped' => $grouped,
        ]);
    }

    public function complete(int $id): ResponseInterface
    {
        $payload = (array) $this->request->getJSON(true);

        try {
            $result = service('studyReview')->complete($this->userId(), $id, $payload);

            return $this->jsonResponse(true, $result, 'Revisão concluída. Bom trabalho!');
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }

    public function reschedule(int $id): ResponseInterface
    {
        $payload = (array) $this->request->getJSON(true);

        try {
            $review = service('studyReview')->reschedule($this->userId(), $id, (string) ($payload['due_date'] ?? ''));

            return $this->jsonResponse(true, ['review' => $review], 'Revisão reagendada.');
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }

    public function skip(int $id): ResponseInterface
    {
        try {
            $review = service('studyReview')->skip($this->userId(), $id);

            return $this->jsonResponse(true, ['review' => $review], 'Revisão ignorada.');
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }
}
