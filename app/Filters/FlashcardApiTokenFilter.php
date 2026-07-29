<?php

declare(strict_types=1);

namespace App\Filters;

use App\Services\Flashcard\ApiTokenContext;
use App\Services\Flashcard\FlashcardApiTokenService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Flashcards as FlashcardsConfig;

/**
 * Autenticação da API externa de flashcards por token Bearer, com limite de
 * requisições por minuto.
 *
 * O token nunca é registrado em log — apenas o identificador do registro.
 */
class FlashcardApiTokenFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $service = new FlashcardApiTokenService();
        $token   = $service->verify($this->bearerToken($request));

        if ($token === null) {
            return $this->deny(401, 'Token ausente, inválido, revogado ou expirado.');
        }

        // O argumento da rota usa o nome curto ("import", "read") porque o
        // roteador do CodeIgniter separa alias e argumentos por dois-pontos.
        $shortNames = [
            'import'     => FlashcardApiTokenService::SCOPE_IMPORT,
            'create'     => FlashcardApiTokenService::SCOPE_CREATE,
            'read'       => FlashcardApiTokenService::SCOPE_READ,
            'categories' => FlashcardApiTokenService::SCOPE_CATEGORIES,
            'subjects'   => FlashcardApiTokenService::SCOPE_SUBJECTS,
            'sources'    => FlashcardApiTokenService::SCOPE_SOURCES,
        ];

        $requested = is_array($arguments) && $arguments !== [] ? (string) $arguments[0] : 'import';
        $scope     = $shortNames[$requested] ?? FlashcardApiTokenService::SCOPE_IMPORT;

        if (! $service->hasScope($token, $scope)) {
            return $this->deny(403, 'Este token não possui a permissão "' . $scope . '".');
        }

        $config    = config(FlashcardsConfig::class);
        $throttler = service('throttler');

        if ($throttler->check('flashcard-api-' . $token['id'], $config->apiRequestsPerMinute, MINUTE) === false) {
            return $this->deny(429, 'Limite de ' . $config->apiRequestsPerMinute . ' requisições por minuto excedido.')
                ->setHeader('Retry-After', (string) $throttler->getTokenTime());
        }

        $service->registerUse((int) $token['id']);

        // Disponibiliza o token autenticado para o controller.
        ApiTokenContext::set($token);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function bearerToken(RequestInterface $request): ?string
    {
        $header = (string) ($request->getHeaderLine('Authorization') ?: '');

        if (preg_match('/^Bearer\s+(\S+)$/i', trim($header), $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function deny(int $status, string $message): ResponseInterface
    {
        return service('response')
            ->setStatusCode($status)
            ->setJSON(['success' => false, 'message' => $message]);
    }
}
