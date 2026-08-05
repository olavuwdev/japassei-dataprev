<?php

declare(strict_types=1);

namespace App\Filters;

use App\Services\Auth\RememberMeService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = session()->get('user');

        if ($user === null || empty($user['id'])) {
            // Sessão expirada, mas o usuário pediu para continuar conectado.
            $remember = new RememberMeService();

            if (($remembered = $remember->attempt()) !== null) {
                $remember->openSession($remembered);

                return null;
            }

            $path = $request->getUri()->getPath();

            if ($request->hasHeader('X-Requested-With')
                || str_starts_with($path, '/estudos/api')
                || str_starts_with($path, '/flashcards/api')) {
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
