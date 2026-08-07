<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\Auth\RememberMeService;
use CodeIgniter\HTTP\RedirectResponse;

class AuthController extends BaseController
{
    public function loginForm()
    {
        if (session()->get('user')) {
            return redirect()->to(permission_home());
        }

        // Cookie de "manter-me conectado" válido: entra direto.
        $remember = new RememberMeService();

        if (($user = $remember->attempt()) !== null) {
            $remember->openSession($user);

            return redirect()->to(permission_home());
        }

        return view('auth/login');
    }

    public function login()
    {
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required|min_length[6]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $users = new UserModel();
        $user  = $users->where('email', $this->request->getPost('email'))
            ->where('active', 1)
            ->first();

        if ($user === null || ! password_verify((string) $this->request->getPost('password'), $user['password_hash'])) {
            return redirect()->back()->withInput()->with('error', 'E-mail ou senha inválidos.');
        }

        $remember = new RememberMeService();
        $remember->openSession($user);

        if ($this->request->getPost('remember_me')) {
            $remember->remember((int) $user['id']);
        } else {
            // Desmarcou nesta entrada: descarta um token antigo do dispositivo.
            $remember->forget();
        }

        $redirect = session()->get('redirect_url') ?? permission_home();
        session()->remove('redirect_url');

        return redirect()->to($redirect)->with('success', 'Bem-vindo(a) de volta, ' . $user['name'] . '!');
    }

    /**
     * O auto-cadastro foi encerrado: contas passaram a ser criadas apenas em
     * Configurações > Usuários, por quem tem a permissão de gerenciar usuários.
     * A rota continua existindo só para não quebrar links antigos.
     */
    public function registerForm(): RedirectResponse
    {
        if (session()->get('user')) {
            return redirect()->to(permission_home());
        }

        return redirect()->to('/login')
            ->with('warning', 'O cadastro é feito por um administrador. Peça seu acesso a quem gerencia o sistema.');
    }

    public function logout()
    {
        (new RememberMeService())->forget();
        session()->destroy();

        return redirect()->to('/login');
    }
}
