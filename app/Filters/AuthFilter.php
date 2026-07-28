<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = session()->get('user');

        if ($user === null || empty($user['id'])) {
            if ($request->hasHeader('X-Requested-With') || str_starts_with($request->getUri()->getPath(), '/estudos/api')) {
                return service('response')
                    ->setStatusCode(401)
                    ->setJSON(['ok' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
            }

            session()->set('redirect_url', current_url());

            return redirect()->to('/login')->with('warning', 'Faça login para continuar.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
