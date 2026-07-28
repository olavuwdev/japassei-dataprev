<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Cadastra as conquistas iniciais da gamificação. Chave natural: code.
 */
class StudyBadgeSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // [code, title, description, icon, xp_reward]
        $badges = [
            ['first_session', 'Primeira sessão',            'Você concluiu sua primeira sessão de estudos.',                     'play-circle', 10],
            ['first_week',    'Primeira semana completa',   'Você completou todos os dias de estudo de uma semana.',             'calendar',    15],
            ['streak_5',      '5 dias de ofensiva',         'Você manteve sua ofensiva por 5 dias de estudo.',                   'flame',       20],
            ['streak_10',     '10 dias de ofensiva',        'Você manteve sua ofensiva por 10 dias de estudo.',                  'flame',       25],
            ['streak_30',     '30 dias de ofensiva',        'Você manteve sua ofensiva por 30 dias de estudo.',                  'flame',       40],
            ['questions_100', '100 questões respondidas',   'Você registrou 100 questões respondidas.',                          'help-circle', 20],
            ['questions_500', '500 questões respondidas',   'Você registrou 500 questões respondidas.',                          'help-circle', 40],
            ['hours_10',      '10 horas estudadas',         'Você acumulou 10 horas de estudo.',                                 'clock',       25],
            ['hours_50',      '50 horas estudadas',         'Você acumulou 50 horas de estudo.',                                 'clock',       45],
            ['mock_80',       '80% em mini simulado',       'Você alcançou 80% ou mais de acertos em um mini simulado.',         'award',       50],
        ];

        $sortOrder = 0;

        foreach ($badges as [$code, $title, $description, $icon, $xpReward]) {
            $sortOrder++;

            $existing = $this->db->table('study_badges')
                ->where('code', $code)
                ->get()
                ->getRowArray();

            $mutable = [
                'title'       => $title,
                'description' => $description,
                'icon'        => $icon,
                'xp_reward'   => $xpReward,
                'sort_order'  => $sortOrder,
                'active'      => 1,
                'updated_at'  => $now,
            ];

            if ($existing === null) {
                $this->db->table('study_badges')->insert($mutable + [
                    'code'       => $code,
                    'created_at' => $now,
                ]);

                continue;
            }

            $this->db->table('study_badges')->where('id', $existing['id'])->update($mutable);
        }
    }
}
