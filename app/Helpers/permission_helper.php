<?php

declare(strict_types=1);

use App\Services\Auth\Permissions;

/**
 * Permissões do usuário em sessão. São gravadas no login (RememberMeService::openSession)
 * e reescritas quando um administrador altera o próprio acesso.
 *
 * @return list<string>
 */
function user_permissions(): array
{
    $user = session()->get('user');

    return is_array($user['permissions'] ?? null) ? array_values($user['permissions']) : [];
}

/**
 * O usuário em sessão possui a permissão informada?
 */
function user_can(string $permission): bool
{
    return in_array($permission, user_permissions(), true);
}

/**
 * Primeira página que o usuário em sessão pode abrir. Serve de destino quando
 * ele cai numa rota sem permissão — evita o laço de redirecionar para uma tela
 * igualmente bloqueada.
 */
function permission_home(): string
{
    if (user_can(Permissions::ESTUDOS)) {
        return site_url('estudos');
    }

    if (user_can(Permissions::FLASHCARDS)) {
        return site_url('flashcards');
    }

    if (user_can(Permissions::USUARIOS)) {
        return site_url('estudos/usuarios');
    }

    return site_url('logout');
}
