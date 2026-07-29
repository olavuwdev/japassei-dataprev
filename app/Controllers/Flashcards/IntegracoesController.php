<?php

declare(strict_types=1);

namespace App\Controllers\Flashcards;

use App\Controllers\BaseController;
use App\Models\FlashcardImportModel;
use App\Models\FlashcardModel;
use App\Services\Flashcard\FlashcardApiTokenService;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Flashcards as FlashcardsConfig;
use RuntimeException;

/**
 * Tokens de integração, documentação copiável e importações recebidas.
 */
class IntegracoesController extends BaseController
{
    public function index(): string
    {
        $service = service('flashcardToken');
        $tokens  = $service->listForUser($this->userId());

        foreach ($tokens as &$token) {
            $token['masked'] = $service->mask($token);
            $token['scopes'] = json_decode((string) $token['scopes'], true) ?: [];
        }

        $imports = (new FlashcardImportModel())
            ->where('user_id', $this->userId())
            ->orderBy('id', 'DESC')
            ->findAll(30);

        return view('flashcards/integracoes', [
            'tokens'    => $tokens,
            'imports'   => $imports,
            'endpoint'  => site_url('api/v1/flashcards/import'),
            'scopes'    => FlashcardApiTokenService::SCOPES,
            'limits'    => config(FlashcardsConfig::class),
            'newToken'  => session()->getFlashdata('new_token'),
        ]);
    }

    public function createToken(): ResponseInterface
    {
        $payload = $this->jsonPayload();

        try {
            $result = service('flashcardToken')->create(
                $this->userId(),
                (string) ($payload['name'] ?? ''),
                (array) ($payload['scopes'] ?? [FlashcardApiTokenService::SCOPE_IMPORT]),
                filter_var($payload['requires_approval'] ?? true, FILTER_VALIDATE_BOOL),
                ! empty($payload['expires_at']) ? (string) $payload['expires_at'] : null
            );
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }

        // O valor completo aparece uma única vez, aqui.
        return $this->jsonResponse(true, [
            'token'  => $result['token'],
            'record' => array_diff_key($result['record'], ['token_hash' => null]),
        ], 'Token criado. Copie-o agora: ele não será exibido novamente.');
    }

    public function revokeToken(string $uuid): ResponseInterface
    {
        try {
            service('flashcardToken')->revoke($this->userId(), $uuid);

            return $this->jsonResponse(true, [], 'Token revogado.');
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 404);
        }
    }

    public function documentation(): string
    {
        return view('flashcards/documentacao', [
            'endpoint' => site_url('api/v1/flashcards/import'),
            'baseUrl'  => rtrim(site_url(), '/'),
            'limits'   => config(FlashcardsConfig::class),
        ]);
    }

    /**
     * Cartões importados que aguardam aprovação do usuário.
     */
    public function pending(): string
    {
        $result = service('flashcard')->listCards(
            $this->userId(),
            ['status' => FlashcardModel::STATUS_PENDING_APPROVAL],
            max(1, (int) ($this->request->getGet('pagina') ?? 1)),
            30
        );

        return view('flashcards/importacoes', $result);
    }

}
