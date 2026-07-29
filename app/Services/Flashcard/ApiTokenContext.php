<?php

declare(strict_types=1);

namespace App\Services\Flashcard;

/**
 * Transporta o token autenticado do filtro para o controller dentro da mesma
 * requisição. Não guarda o valor do token — apenas o registro do banco.
 */
final class ApiTokenContext
{
    /** @var array<string, mixed>|null */
    private static ?array $token = null;

    /**
     * @param array<string, mixed> $token
     */
    public static function set(array $token): void
    {
        self::$token = $token;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(): ?array
    {
        return self::$token;
    }

    public static function clear(): void
    {
        self::$token = null;
    }
}
