<?php

declare(strict_types=1);

namespace App\Controllers\Estudos;

use App\Controllers\BaseController;
use App\Models\StudyUserSettingModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Preferências do usuário: meta diária, dias de estudo, intervalos de revisão,
 * automações, notificações e fuso horário.
 */
class ConfiguracoesController extends BaseController
{
    public const TIMEZONES = [
        'America/Noronha'     => 'Fernando de Noronha (UTC−2)',
        'America/Sao_Paulo'   => 'Brasília / São Paulo (UTC−3)',
        'America/Fortaleza'   => 'Fortaleza (UTC−3)',
        'America/Recife'      => 'Recife (UTC−3)',
        'America/Bahia'       => 'Salvador (UTC−3)',
        'America/Manaus'      => 'Manaus (UTC−4)',
        'America/Cuiaba'      => 'Cuiabá (UTC−4)',
        'America/Porto_Velho' => 'Porto Velho (UTC−4)',
        'America/Boa_Vista'   => 'Boa Vista (UTC−4)',
        'America/Rio_Branco'  => 'Rio Branco (UTC−5)',
    ];

    public function index(): string
    {
        $settings = (new StudyUserSettingModel())->where('user_id', $this->userId())->first();

        $weekdays  = $settings !== null ? json_decode((string) ($settings['study_weekdays'] ?? ''), true) : null;
        $intervals = $settings !== null ? json_decode((string) ($settings['review_intervals'] ?? ''), true) : null;

        return view('estudos/configuracoes', [
            'settings'  => $settings,
            'weekdays'  => is_array($weekdays) && $weekdays !== [] ? array_map('intval', $weekdays) : [1, 2, 3, 4, 5],
            'intervals' => is_array($intervals) && count($intervals) >= 3 ? array_map('intval', array_values($intervals)) : [1, 7, 30],
            'timezones' => self::TIMEZONES,
            'plan'      => service('studyPlan')->getActivePlan($this->userId()),
        ]);
    }

    public function save(): RedirectResponse
    {
        $goal = (int) $this->request->getPost('daily_goal_minutes');

        if ($goal < 15 || $goal > 480) {
            return redirect()->back()->withInput()
                ->with('error', 'A meta diária deve estar entre 15 e 480 minutos.');
        }

        $weekdays = array_values(array_unique(array_filter(
            array_map('intval', (array) $this->request->getPost('study_weekdays')),
            static fn (int $day): bool => $day >= 1 && $day <= 7
        )));
        sort($weekdays);

        if ($weekdays === []) {
            return redirect()->back()->withInput()
                ->with('error', 'Selecione pelo menos um dia de estudo.');
        }

        $intervals = [
            (int) $this->request->getPost('review_interval_1'),
            (int) $this->request->getPost('review_interval_2'),
            (int) $this->request->getPost('review_interval_3'),
        ];

        if ($intervals[0] <= 0 || $intervals[0] >= $intervals[1] || $intervals[1] >= $intervals[2]) {
            return redirect()->back()->withInput()
                ->with('error', 'Os intervalos de revisão devem ser maiores que zero e crescentes (ex.: 1, 7 e 30).');
        }

        $timezone = (string) $this->request->getPost('timezone');
        if (! array_key_exists($timezone, self::TIMEZONES)) {
            $timezone = 'America/Fortaleza';
        }

        $data = [
            'daily_goal_minutes'    => $goal,
            'timezone'              => $timezone,
            'study_weekdays'        => json_encode($weekdays),
            'review_intervals'      => json_encode($intervals),
            'auto_complete_tasks'   => $this->request->getPost('auto_complete_tasks') !== null ? 1 : 0,
            'notifications_enabled' => $this->request->getPost('notifications_enabled') !== null ? 1 : 0,
        ];

        $model    = new StudyUserSettingModel();
        $existing = $model->where('user_id', $this->userId())->first();

        if ($existing !== null) {
            $model->update($existing['id'], $data);
        } else {
            $data['user_id'] = $this->userId();
            $model->insert($data);
        }

        return redirect()->to(site_url('estudos/configuracoes'))
            ->with('success', 'Configurações salvas com sucesso.');
    }
}
