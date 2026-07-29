<?php

declare(strict_types=1);

namespace App\Services\Flashcard;

use App\Models\FlashcardApiTokenModel;
use RuntimeException;

/**
 * Emissão, verificação e revogação dos tokens da API externa.
 *
 * O token só existe em texto puro no instante da criação: o banco guarda
 * apenas o hash SHA-256.
 */
class FlashcardApiTokenService
{
    public const PREFIX = 'flc_live_';

    public const SCOPE_IMPORT     = 'flashcards:import';
    public const SCOPE_CREATE     = 'flashcards:create';
    public const SCOPE_READ       = 'flashcards:read';
    public const SCOPE_CATEGORIES = 'categories:create';
    public const SCOPE_SUBJECTS   = 'subjects:create';
    public const SCOPE_SOURCES    = 'sources:create';

    public const SCOPES = [
        self::SCOPE_IMPORT     => 'Importar flashcards',
        self::SCOPE_CREATE     => 'Criar flashcards',
        self::SCOPE_READ       => 'Consultar flashcards',
        self::SCOPE_CATEGORIES => 'Criar categorias',
        self::SCOPE_SUBJECTS   => 'Criar assuntos',
        self::SCOPE_SOURCES    => 'Criar fontes de estudo',
    ];

    public function __construct(private ?FlashcardAuditService $audit = null)
    {
        $this->audit ??= new FlashcardAuditService();
    }

    /**
     * Cria um token. O valor completo é devolvido apenas nesta chamada.
     *
     * @param list<string> $scopes
     *
     * @return array{token:string, record:array<string,mixed>}
     */
    public function create(int $userId, string $name, array $scopes = [], bool $requiresApproval = true, ?string $expiresAt = null): array
    {
        $name = trim($name);

        if ($name === '') {
            throw new RuntimeException('Informe um nome para identificar a integração.');
        }

        $scopes = array_values(array_intersect($scopes, array_keys(self::SCOPES)));

        if ($scopes === []) {
            $scopes = [self::SCOPE_IMPORT];
        }

        $secret = bin2hex(random_bytes(24));
        $token  = self::PREFIX . $secret;

        $model = new FlashcardApiTokenModel();

        $id = (int) $model->insert([
            'uuid'              => $this->uuid(),
            'user_id'           => $userId,
            'name'              => mb_substr($name, 0, 100),
            'prefix'            => self::PREFIX,
            'last_four'         => substr($secret, -4),
            'token_hash'        => $this->hash($token),
            'scopes'            => json_encode($scopes, JSON_UNESCAPED_UNICODE),
            'requires_approval' => $requiresApproval ? 1 : 0,
            'active'            => 1,
            'expires_at'        => $expiresAt,
        ], true);

        $this->audit->log($userId, FlashcardAuditService::TOKEN_CREATED, 'token', $id, ['name' => $name]);

        return ['token' => $token, 'record' => $model->find($id)];
    }

    /**
     * Localiza o token pelo valor recebido no cabeçalho Authorization.
     * Devolve null quando ausente, inválido, revogado ou expirado.
     *
     * @return array<string, mixed>|null
     */
    public function verify(?string $rawToken): ?array
    {
        if ($rawToken === null || trim($rawToken) === '') {
            return null;
        }

        $token = trim($rawToken);

        if (! str_starts_with($token, self::PREFIX)) {
            return null;
        }

        $record = (new FlashcardApiTokenModel())
            ->where('token_hash', $this->hash($token))
            ->where('active', 1)
            ->first();

        if ($record === null) {
            return null;
        }

        if (! empty($record['expires_at']) && strtotime((string) $record['expires_at'] . ' UTC') < time()) {
            return null;
        }

        return $record;
    }

    /**
     * @param array<string, mixed> $token
     */
    public function hasScope(array $token, string $scope): bool
    {
        $scopes = json_decode((string) ($token['scopes'] ?? '[]'), true);

        if (! is_array($scopes)) {
            return false;
        }

        if (in_array($scope, $scopes, true)) {
            return true;
        }

        // O escopo de importação do MVP cobre tudo o que a importação precisa:
        // consultar a taxonomia e criar disciplina, categoria, assunto e fonte.
        if (in_array(self::SCOPE_IMPORT, $scopes, true)) {
            return in_array($scope, [
                self::SCOPE_CREATE,
                self::SCOPE_READ,
                self::SCOPE_CATEGORIES,
                self::SCOPE_SUBJECTS,
                self::SCOPE_SOURCES,
            ], true);
        }

        return false;
    }

    public function registerUse(int $tokenId): void
    {
        $model = new FlashcardApiTokenModel();

        $model->db->table($model->table)
            ->where('id', $tokenId)
            ->set('request_count', 'request_count + 1', false)
            ->set('last_used_at', gmdate('Y-m-d H:i:s'))
            ->update();
    }

    public function revoke(int $userId, string $uuid): void
    {
        $model  = new FlashcardApiTokenModel();
        $record = $model->where('user_id', $userId)->where('uuid', $uuid)->first();

        if ($record === null) {
            throw new RuntimeException('Token não encontrado.');
        }

        $model->update((int) $record['id'], [
            'active'     => 0,
            'revoked_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->audit->log($userId, FlashcardAuditService::TOKEN_REVOKED, 'token', (int) $record['id']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $userId): array
    {
        return (new FlashcardApiTokenModel())
            ->where('user_id', $userId)
            ->orderBy('active', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    /**
     * Exibição segura: nunca mostra o token completo.
     *
     * @param array<string, mixed> $record
     */
    public function mask(array $record): string
    {
        return $record['prefix'] . str_repeat('•', 12) . ($record['last_four'] ?? '');
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function uuid(): string
    {
        $bytes    = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
