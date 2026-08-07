<?php

declare(strict_types=1);

namespace App\Controllers\Estudos;

use App\Controllers\BaseController;
use App\Models\StudyStreakModel;
use App\Models\StudyUserSettingModel;
use App\Models\UserModel;
use App\Models\UserPermissionModel;
use App\Services\Auth\Permissions;
use App\Services\Auth\RememberMeService;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Cadastro de usuários e atribuição de permissões por módulo.
 *
 * Toda a área exige a permissão `usuarios` (aplicada no arquivo de rotas).
 */
class UsuariosController extends BaseController
{
    public function index(): string
    {
        $users = (new UserModel())->orderBy('name', 'ASC')->findAll();
        $ids   = array_map(static fn (array $user): int => (int) $user['id'], $users);

        return view('estudos/usuarios', [
            'users'       => $users,
            'permissions' => (new UserPermissionModel())->forUsers($ids),
            'catalog'     => Permissions::CATALOG,
            'currentId'   => $this->userId(),
        ]);
    }

    public function create(): string
    {
        return view('estudos/usuarios_form', [
            'user'    => null,
            'granted' => [Permissions::ESTUDOS, Permissions::FLASHCARDS],
            'catalog' => Permissions::CATALOG,
        ]);
    }

    public function store(): RedirectResponse
    {
        $rules = [
            'name'             => 'required|min_length[3]|max_length[120]',
            'email'            => 'required|valid_email|is_unique[users.email]',
            'password'         => 'required|min_length[6]',
            'password_confirm' => 'required|matches[password]',
        ];

        if (! $this->validate($rules, $this->validationMessages())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $users  = new UserModel();
        $userId = (int) $users->insert([
            'name'          => $this->request->getPost('name'),
            'email'         => $this->request->getPost('email'),
            'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
            'active'        => $this->request->getPost('active') !== null ? 1 : 0,
        ]);

        if ($userId === 0) {
            return redirect()->back()->withInput()->with('error', 'Não foi possível criar o usuário. Tente novamente.');
        }

        $this->createDefaults($userId);
        (new UserPermissionModel())->sync($userId, $this->postedPermissions());

        return redirect()->to(site_url('estudos/usuarios'))
            ->with('success', 'Usuário criado com sucesso.');
    }

    public function edit(int $id): string|RedirectResponse
    {
        $user = (new UserModel())->find($id);

        if ($user === null) {
            return redirect()->to(site_url('estudos/usuarios'))->with('error', 'Usuário não encontrado.');
        }

        return view('estudos/usuarios_form', [
            'user'    => $user,
            'granted' => (new UserPermissionModel())->forUser($id),
            'catalog' => Permissions::CATALOG,
        ]);
    }

    public function update(int $id): RedirectResponse
    {
        $users = new UserModel();
        $user  = $users->find($id);

        if ($user === null) {
            return redirect()->to(site_url('estudos/usuarios'))->with('error', 'Usuário não encontrado.');
        }

        $rules = [
            'name'  => 'required|min_length[3]|max_length[120]',
            'email' => 'required|valid_email|is_unique[users.email,id,' . $id . ']',
        ];

        $password = (string) $this->request->getPost('password');

        if ($password !== '') {
            $rules['password']         = 'min_length[6]';
            $rules['password_confirm'] = 'matches[password]';
        }

        if (! $this->validate($rules, $this->validationMessages())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $codes  = $this->postedPermissions();
        $active = $this->request->getPost('active') !== null ? 1 : 0;

        // Trava de segurança: sem ela um administrador se removeria do próprio
        // painel e ninguém mais conseguiria devolver o acesso pela interface.
        if ($id === $this->userId()) {
            if (! in_array(Permissions::USUARIOS, $codes, true)) {
                return redirect()->back()->withInput()
                    ->with('error', 'Você não pode remover a sua própria permissão de gerenciar usuários.');
            }

            if ($active === 0) {
                return redirect()->back()->withInput()
                    ->with('error', 'Você não pode desativar a sua própria conta.');
            }
        }

        $data = [
            'name'   => $this->request->getPost('name'),
            'email'  => $this->request->getPost('email'),
            'active' => $active,
        ];

        if ($password !== '') {
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        // As regras do UserModel são pensadas para o cadastro: `is_unique[users.email]`
        // ali não ignora o próprio registro e reprovaria qualquer edição que
        // mantivesse o e-mail. A validação da edição já foi feita acima, com o
        // `ignore` correto.
        $users->skipValidation(true)->update($id, $data);
        (new UserPermissionModel())->sync($id, $codes);

        // Quem editou a si mesmo precisa ver as permissões novas já nesta sessão.
        RememberMeService::refreshSession($id);

        return redirect()->to(site_url('estudos/usuarios'))
            ->with('success', 'Usuário atualizado com sucesso.');
    }

    public function delete(int $id): RedirectResponse
    {
        if ($id === $this->userId()) {
            return redirect()->to(site_url('estudos/usuarios'))
                ->with('error', 'Você não pode excluir a sua própria conta.');
        }

        $users = new UserModel();

        if ($users->find($id) === null) {
            return redirect()->to(site_url('estudos/usuarios'))->with('error', 'Usuário não encontrado.');
        }

        $users->delete($id);

        return redirect()->to(site_url('estudos/usuarios'))
            ->with('success', 'Usuário excluído.');
    }

    /**
     * Permissões marcadas no formulário, já validadas contra o catálogo.
     *
     * @return list<string>
     */
    private function postedPermissions(): array
    {
        return Permissions::normalize((array) $this->request->getPost('permissions'));
    }

    /**
     * Preferências e ofensiva iniciais — as mesmas que o antigo auto-cadastro
     * criava, para que o usuário novo já abra as telas sem estado faltando.
     */
    private function createDefaults(int $userId): void
    {
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
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function validationMessages(): array
    {
        return [
            'email' => [
                'is_unique' => 'Este e-mail já está cadastrado.',
            ],
            'password_confirm' => [
                'matches' => 'As senhas não conferem.',
            ],
        ];
    }
}
