<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\StudyStreakModel;
use App\Models\StudyUserSettingModel;
use App\Models\UserModel;
use App\Services\Auth\RememberMeService;

class AuthController extends BaseController
{
    public function loginForm()
    {
        if (session()->get('user')) {
            return redirect()->to('/estudos');
        }

        // Cookie de "manter-me conectado" válido: entra direto.
        $remember = new RememberMeService();

        if (($user = $remember->attempt()) !== null) {
            $remember->openSession($user);

            return redirect()->to('/estudos');
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

        $redirect = session()->get('redirect_url') ?? '/estudos';
        session()->remove('redirect_url');

        return redirect()->to($redirect)->with('success', 'Bem-vindo(a) de volta, ' . $user['name'] . '!');
    }

    public function registerForm(): string
    {
        if (session()->get('user')) {
            return (string) redirect()->to('/estudos');
        }

        return view('auth/register');
    }

    public function register()
    {
        $rules = [
            'name'             => 'required|min_length[3]|max_length[120]',
            'email'            => 'required|valid_email|is_unique[users.email]',
            'password'         => 'required|min_length[6]',
            'password_confirm' => 'required|matches[password]',
        ];

        $messages = [
            'email' => [
                'is_unique' => 'Este e-mail já está cadastrado.',
            ],
            'password_confirm' => [
                'matches' => 'As senhas não conferem.',
            ],
        ];

        if (! $this->validate($rules, $messages)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $users  = new UserModel();
        $userId = $users->insert([
            'name'          => $this->request->getPost('name'),
            'email'         => $this->request->getPost('email'),
            'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
            'active'        => 1,
        ]);

        if (! $userId) {
            return redirect()->back()->withInput()->with('error', 'Não foi possível criar a conta. Tente novamente.');
        }

        (new StudyUserSettingModel())->insert([
            'user_id'               => $userId,
            'daily_goal_minutes'    => 60,
            'timezone'              => 'America/Fortaleza',
            'study_weekdays'        => json_encode([1, 2, 3, 4, 5]),
            'review_intervals'      => json_encode([1, 7, 30]),
            'auto_complete_tasks'   => 0,
            'notifications_enabled' => 1,
        ]);

        (new StudyStreakModel())->insert([
            'user_id'              => $userId,
            'current_streak'       => 0,
            'best_streak'          => 0,
            'total_qualified_days' => 0,
        ]);

        (new RememberMeService())->openSession([
            'id'    => $userId,
            'name'  => $this->request->getPost('name'),
            'email' => $this->request->getPost('email'),
        ]);

        return redirect()->to('/estudos')->with('success', 'Conta criada! Bons estudos.');
    }

    public function logout()
    {
        (new RememberMeService())->forget();
        session()->destroy();

        return redirect()->to('/login');
    }
}
