<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\FlashcardImportModel;
use App\Models\StudySubjectModel;
use App\Models\StudyTopicModel;
use App\Services\Flashcard\ApiTokenContext;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * API REST externa: recebe flashcards gerados por ChatGPT, Gemini ou qualquer
 * cliente HTTP. Autenticada pelo filtro `flashcardApi` (token Bearer).
 */
class FlashcardApiController extends BaseController
{
    public function import(): ResponseInterface
    {
        $token = ApiTokenContext::get();

        if ($token === null) {
            return $this->apiResponse(401, ['success' => false, 'message' => 'Token não autenticado.']);
        }

        // getJSON() lança em corpo malformado; a API precisa responder 400 limpo.
        $payload = json_decode((string) $this->request->getBody(), true);

        if (! is_array($payload) || $payload === []) {
            return $this->apiResponse(400, [
                'success' => false,
                'message' => 'Corpo da requisição inválido: envie um JSON válido em UTF-8. '
                    . (json_last_error() !== JSON_ERROR_NONE ? json_last_error_msg() : ''),
            ]);
        }

        try {
            $result = service('flashcardImport')->import((int) $token['user_id'], $payload, [
                'token_id'          => (int) $token['id'],
                'requires_approval' => (bool) $token['requires_approval'],
                'idempotency_key'   => $this->idempotencyKey(),
                'ip'                => $this->request->getIPAddress(),
                'user_agent'        => $this->request->getUserAgent()->getAgentString(),
            ]);

            return $this->apiResponse($result['status'], $result['body']);
        } catch (Throwable $e) {
            log_message('error', 'Falha na importação externa: {msg}', ['msg' => $e->getMessage()]);

            return $this->apiResponse(500, [
                'success' => false,
                'message' => 'Erro interno ao processar a importação. Tente novamente em instantes.',
            ]);
        }
    }

    /**
     * Consulta o resultado de uma importação anterior.
     */
    public function status(string $uuid): ResponseInterface
    {
        $token = ApiTokenContext::get();

        $import = (new FlashcardImportModel())
            ->where('user_id', (int) $token['user_id'])
            ->where('uuid', $uuid)
            ->first();

        if ($import === null) {
            return $this->apiResponse(404, ['success' => false, 'message' => 'Importação não encontrada.']);
        }

        $body = json_decode((string) $import['response_payload'], true);

        return $this->apiResponse(200, is_array($body) ? $body : [
            'success' => $import['status'] !== FlashcardImportModel::STATUS_ERROR,
            'status'  => $import['status'],
            'summary' => [
                'received'   => (int) $import['received_count'],
                'created'    => (int) $import['created_count'],
                'duplicates' => (int) $import['duplicate_count'],
                'rejected'   => (int) $import['rejected_count'],
            ],
        ]);
    }

    /**
     * Disciplinas disponíveis, para a IA escolher um nome já existente.
     */
    public function disciplines(): ResponseInterface
    {
        $rows = (new StudySubjectModel())
            ->select('id, name')
            ->where('active', 1)
            ->orderBy('name', 'ASC')
            ->findAll(500);

        return $this->apiResponse(200, ['success' => true, 'disciplines' => $rows]);
    }

    /**
     * Categorias: tópicos de primeiro nível de uma disciplina.
     */
    public function categories(): ResponseInterface
    {
        return $this->apiResponse(200, [
            'success'    => true,
            'categories' => $this->topics(true),
        ]);
    }

    public function subjects(): ResponseInterface
    {
        return $this->apiResponse(200, [
            'success'  => true,
            'subjects' => $this->topics(false),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topics(bool $rootOnly): array
    {
        $model = (new StudyTopicModel())
            ->select('id, subject_id, parent_id, name')
            ->where('active', 1);

        if ($rootOnly) {
            $model->where('parent_id', null);
        }

        $disciplineId = $this->request->getGet('discipline_id');

        if ($disciplineId !== null && $disciplineId !== '') {
            $model->where('subject_id', (int) $disciplineId);
        }

        return $model->orderBy('name', 'ASC')->findAll(1000);
    }

    private function idempotencyKey(): ?string
    {
        $key = trim((string) $this->request->getHeaderLine('Idempotency-Key'));

        return $key === '' ? null : mb_substr($key, 0, 191);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function apiResponse(int $status, array $body): ResponseInterface
    {
        return $this->response
            ->setStatusCode($status)
            ->setJSON($body);
    }
}
