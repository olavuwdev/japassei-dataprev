<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\RememberTokenModel;
use App\Models\UserModel;
use App\Models\UserPermissionModel;

/**
 * Login persistente ("manter-me conectado") por cookie assinado.
 *
 * Padrão selector/validator: o cookie carrega `selector:validator`. O banco
 * guarda o selector em claro (para o índice) e apenas o hash SHA-256 do
 * validator — um vazamento da tabela não permite forjar cookies, e a senha
 * do usuário nunca é gravada nem trafega neste fluxo.
 *
 * O validator é rotacionado a cada uso. Um selector válido acompanhado de
 * validator errado indica cookie roubado: todas as sessões persistentes
 * daquele usuário são revogadas.
 */
class RememberMeService
{
    public const COOKIE = 'japassei_remember';

    /** Validade do token, renovada a cada uso. */
    public const TTL_DAYS = 30;

    /**
     * Emite um novo token para o usuário e grava o cookie.
     */
    public function remember(int $userId): void
    {
        $model = new RememberTokenModel();

        // Higiene: remove tokens vencidos do próprio usuário a cada emissão.
        $model->where('user_id', $userId)
            ->where('expires_at <', gmdate('Y-m-d H:i:s'))
            ->delete();

        $selector  = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $request   = service('request');

        $model->insert([
            'user_id'        => $userId,
            'selector'       => $selector,
            'validator_hash' => $this->hash($validator),
            'user_agent'     => mb_substr((string) $request->getUserAgent(), 0, 255),
            'ip_address'     => $request->getIPAddress(),
            'expires_at'     => gmdate('Y-m-d H:i:s', time() + $this->ttlSeconds()),
        ]);

        $this->writeCookie($selector . ':' . $validator);
    }

    /**
     * Valida o cookie e devolve o usuário correspondente, rotacionando o
     * validator. Devolve null quando não há cookie válido.
     *
     * @return array<string, mixed>|null
     */
    public function attempt(): ?array
    {
        [$selector, $validator] = $this->readCookie();

        if ($selector === null || $validator === null) {
            return null;
        }

        $model  = new RememberTokenModel();
        $record = $model->where('selector', $selector)->first();

        if ($record === null) {
            $this->clearCookie();

            return null;
        }

        if (! hash_equals((string) $record['validator_hash'], $this->hash($validator))) {
            // Selector existe, validator não confere: cookie comprometido.
            $this->revokeAllForUser((int) $record['user_id']);
            $this->clearCookie();

            return null;
        }

        if (! empty($record['revoked_at'])) {
            $this->clearCookie();

            return null;
        }

        if (strtotime((string) $record['expires_at'] . ' UTC') < time()) {
            $model->delete((int) $record['id']);
            $this->clearCookie();

            return null;
        }

        $user = (new UserModel())
            ->where('id', (int) $record['user_id'])
            ->where('active', 1)
            ->first();

        if ($user === null) {
            $model->delete((int) $record['id']);
            $this->clearCookie();

            return null;
        }

        // Rotação: o validator usado nunca vale duas vezes.
        $newValidator = bin2hex(random_bytes(32));

        $model->update((int) $record['id'], [
            'validator_hash' => $this->hash($newValidator),
            'last_used_at'   => gmdate('Y-m-d H:i:s'),
            'expires_at'     => gmdate('Y-m-d H:i:s', time() + $this->ttlSeconds()),
        ]);

        $this->writeCookie($selector . ':' . $newValidator);

        return $user;
    }

    /**
     * Revoga apenas o token do dispositivo atual e apaga o cookie.
     */
    public function forget(): void
    {
        [$selector] = $this->readCookie();

        if ($selector !== null) {
            (new RememberTokenModel())
                ->where('selector', $selector)
                ->set('revoked_at', gmdate('Y-m-d H:i:s'))
                ->update();
        }

        $this->clearCookie();
    }

    /**
     * Derruba o login persistente em todos os dispositivos do usuário.
     */
    public function revokeAllForUser(int $userId): void
    {
        (new RememberTokenModel())->where('user_id', $userId)->delete();
    }

    /**
     * Abre a sessão autenticada. Centraliza o formato do payload usado por
     * login, cadastro e restauração via cookie.
     *
     * @param array<string, mixed> $user
     */
    public function openSession(array $user): void
    {
        session()->regenerate();
        session()->set('user', self::sessionPayload((int) $user['id'], (string) $user['name'], (string) $user['email']));
    }

    /**
     * Recarrega da base os dados do usuário em sessão.
     *
     * Usado depois que um administrador altera permissões: sem isso, quem já
     * estava logado continuaria com o acesso antigo até sair e entrar de novo.
     */
    public static function refreshSession(int $userId): void
    {
        $current = session()->get('user');

        if (! is_array($current) || (int) ($current['id'] ?? 0) !== $userId) {
            return;
        }

        $user = (new UserModel())->find($userId);

        if ($user === null) {
            return;
        }

        session()->set('user', self::sessionPayload($userId, (string) $user['name'], (string) $user['email']));
    }

    /**
     * @return array{id: int, name: string, email: string, permissions: list<string>}
     */
    private static function sessionPayload(int $userId, string $name, string $email): array
    {
        return [
            'id'          => $userId,
            'name'        => $name,
            'email'       => $email,
            'permissions' => (new UserPermissionModel())->forUser($userId),
        ];
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function readCookie(): array
    {
        $raw = (string) (service('request')->getCookie($this->cookieName()) ?? '');

        if (! str_contains($raw, ':')) {
            return [null, null];
        }

        [$selector, $validator] = explode(':', $raw, 2);

        if ($selector === '' || $validator === '') {
            return [null, null];
        }

        return [$selector, $validator];
    }

    private function writeCookie(string $value): void
    {
        service('response')->setCookie([
            'name'     => self::COOKIE,
            'value'    => $value,
            'expire'   => $this->ttlSeconds(),
            'path'     => '/',
            'secure'   => service('request')->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function clearCookie(): void
    {
        service('response')->deleteCookie(self::COOKIE, '', '/');
    }

    /**
     * Nome real do cookie na requisição, considerando o prefixo configurado.
     */
    private function cookieName(): string
    {
        return config('Cookie')->prefix . self::COOKIE;
    }

    private function ttlSeconds(): int
    {
        return self::TTL_DAYS * 86400;
    }

    private function hash(string $validator): string
    {
        return hash('sha256', $validator);
    }
}
