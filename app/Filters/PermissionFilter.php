<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Bloqueia rotas cujo módulo o usuário não tem permissão de acessar.
 *
 * Usado sempre depois do `auth` na lista de filtros da rota, para que a sessão
 * já esteja aberta. Vários argumentos valem como "qualquer um destes":
 * `perm:flashcards,usuarios`.
 */
class PermissionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $required = array_filter((array) ($arguments ?? []));

        if ($required === []) {
            return null;
        }

        foreach ($required as $permission) {
            if (user_can((string) $permission)) {
                return null;
            }
        }

        $message = 'Você não tem permissão para acessar esta área.';
        $path    = $request->getUri()->getPath();

        if ($request->hasHeader('X-Requested-With')
            || str_starts_with($path, '/estudos/api')
            || str_starts_with($path, '/flashcards/api')) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON(['ok' => false, 'message' => $message]);
        }

        return redirect()->to(permission_home())->with('error', $message);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
