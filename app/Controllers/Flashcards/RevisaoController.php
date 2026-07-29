<?php

declare(strict_types=1);

namespace App\Controllers\Flashcards;

use App\Controllers\BaseController;
use App\Models\FlashcardSettingModel;
use App\Services\Flashcard\FsrsUnavailableException;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * Sessão de revisão: a tela mais importante do módulo.
 */
class RevisaoController extends BaseController
{
    public function index(): string
    {
        $userId  = $this->userId();
        $filters = [
            'subject_id' => $this->intOrNull($this->request->getGet('disciplina')),
            'topic_id'   => $this->intOrNull($this->request->getGet('assunto')),
        ];

        return view('flashcards/revisar', [
            'counts'   => service('flashcardQueue')->counts($userId, $filters),
            'settings' => (new FlashcardSettingModel())->forUser($userId),
            'filters'  => $filters,
            'taxonomy' => service('flashcard')->taxonomy(),
        ]);
    }

    public function createSession(): ResponseInterface
    {
        $payload = $this->jsonPayload();

        $session = service('flashcardSession')->start($this->userId(), [
            'subject_id' => $this->intOrNull($payload['subject_id'] ?? null),
            'topic_id'   => $this->intOrNull($payload['topic_id'] ?? null),
        ]);

        return $this->jsonResponse(true, ['session' => $session]);
    }

    public function next(string $uuid): ResponseInterface
    {
        try {
            return $this->jsonResponse(true, service('flashcardSession')->next($this->userId(), $uuid));
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 404);
        }
    }

    public function review(string $uuid): ResponseInterface
    {
        $payload = $this->jsonPayload();

        try {
            $result = service('flashcardSession')->review(
                $this->userId(),
                $uuid,
                (int) ($payload['card_id'] ?? 0),
                (int) ($payload['rating'] ?? 0),
                (string) ($payload['request_uuid'] ?? ''),
                isset($payload['state_version']) ? (int) $payload['state_version'] : null,
                isset($payload['question_ms']) ? (int) $payload['question_ms'] : null,
                isset($payload['answer_ms']) ? (int) $payload['answer_ms'] : null,
            );

            return $this->jsonResponse(true, $result);
        } catch (FsrsUnavailableException $e) {
            // O PRD proíbe registrar a avaliação com cálculo aproximado.
            return $this->jsonResponse(
                false,
                ['fsrs_offline' => true],
                'Não foi possível calcular a próxima revisão. Sua resposta não foi registrada. Tente novamente.',
                503
            );
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 409);
        }
    }

    public function undo(string $uuid): ResponseInterface
    {
        try {
            $state = service('flashcardSession')->undo($this->userId(), $uuid);

            return $this->jsonResponse(true, ['state' => $state], 'Última resposta desfeita.');
        } catch (FsrsUnavailableException $e) {
            return $this->jsonResponse(false, [], 'Não foi possível desfazer agora: o serviço de agendamento está indisponível.', 503);
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }

    public function finish(string $uuid): ResponseInterface
    {
        try {
            return $this->jsonResponse(true, service('flashcardSession')->finish($this->userId(), $uuid));
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 404);
        }
    }

    public function history(): string
    {
        $page = max(1, (int) ($this->request->getGet('pagina') ?? 1));

        return view('flashcards/historico', service('flashcardStatistics')->reviewHistory($this->userId(), $page));
    }


    private function intOrNull($value): ?int
    {
        return $value === null || $value === '' || (int) $value === 0 ? null : (int) $value;
    }
}
