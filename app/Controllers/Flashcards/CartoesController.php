<?php

declare(strict_types=1);

namespace App\Controllers\Flashcards;

use App\Controllers\BaseController;
use App\Models\FlashcardModel;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * Cadastro manual, listagem e edição de cartões.
 */
class CartoesController extends BaseController
{
    public function index(): string
    {
        $filters = [
            'subject_id' => $this->request->getGet('disciplina'),
            'topic_id'   => $this->request->getGet('assunto'),
            'card_type'  => $this->request->getGet('tipo'),
            'search'     => $this->request->getGet('busca'),
            'suspended'  => $this->request->getGet('suspensos'),
            'flagged'    => $this->request->getGet('problematicos'),
            'status'     => $this->request->getGet('status') ?: FlashcardModel::STATUS_ACTIVE,
        ];

        $page   = max(1, (int) ($this->request->getGet('pagina') ?? 1));
        $result = service('flashcard')->listCards($this->userId(), $filters, $page, 20);

        return view('flashcards/cartoes', array_merge($result, [
            'filters'  => $filters,
            'taxonomy' => service('flashcard')->taxonomy(),
        ]));
    }

    public function create(): ResponseInterface
    {
        $payload = $this->jsonPayload();

        try {
            $result = service('flashcard')->createNote($this->userId(), $payload);

            if ($result['errors'] !== []) {
                return $this->jsonResponse(false, ['errors' => $result['errors']], implode(' ', array_column($result['errors'], 'message')), 422);
            }

            if ($result['cards'] === []) {
                return $this->jsonResponse(false, [], 'Este cartão já existe na sua coleção.', 422);
            }

            return $this->jsonResponse(true, [
                'cards'      => $result['cards'],
                'duplicates' => count($result['duplicates']),
            ], count($result['cards']) === 1 ? 'Cartão criado!' : count($result['cards']) . ' cartões criados!');
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }

    public function show(int $id): ResponseInterface
    {
        try {
            return $this->jsonResponse(true, ['card' => service('flashcard')->findOwned($this->userId(), $id)]);
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 404);
        }
    }

    public function update(int $id): ResponseInterface
    {
        try {
            $card = service('flashcard')->updateCard($this->userId(), $id, $this->jsonPayload());

            return $this->jsonResponse(true, ['card' => $card], 'Cartão atualizado.');
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }

    public function delete(int $id): ResponseInterface
    {
        try {
            service('flashcard')->deleteCard($this->userId(), $id);

            return $this->jsonResponse(true, [], 'Cartão excluído.');
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }

    public function suspend(int $id): ResponseInterface
    {
        try {
            $payload   = $this->jsonPayload();
            $suspended = array_key_exists('suspended', $payload) ? (bool) $payload['suspended'] : null;
            $card      = service('flashcard')->toggleSuspend($this->userId(), $id, $suspended);

            return $this->jsonResponse(
                true,
                ['card' => $card],
                $card['suspended'] ? 'Cartão suspenso.' : 'Cartão reativado.'
            );
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }

    /**
     * Aprovação em lote de cartões importados que aguardam validação.
     */
    public function approve(): ResponseInterface
    {
        $ids = array_map('intval', (array) ($this->jsonPayload()['ids'] ?? []));

        if ($ids === []) {
            return $this->jsonResponse(false, [], 'Selecione ao menos um cartão.', 422);
        }

        $model = new FlashcardModel();

        $model->where('user_id', $this->userId())
            ->whereIn('id', $ids)
            ->where('status', FlashcardModel::STATUS_PENDING_APPROVAL)
            ->set(['status' => FlashcardModel::STATUS_ACTIVE, 'updated_at' => gmdate('Y-m-d H:i:s')])
            ->update();

        $affected = $model->db->affectedRows();

        // Cartões aprovados entram na fila de estudos.
        $model->db->table('study_flashcard_states')
            ->where('user_id', $this->userId())
            ->whereIn('flashcard_id', $ids)
            ->update(['in_queue' => 1]);

        return $this->jsonResponse(true, ['approved' => $affected], $affected . ' cartão(ões) aprovado(s).');
    }

}
