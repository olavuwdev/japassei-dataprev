<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Cria o usuário padrão do módulo de estudos, suas configurações
 * e o registro inicial de ofensiva (streak). Idempotente.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $now   = date('Y-m-d H:i:s');
        $email = 'estudante@japassei.local';

        $user = $this->db->table('users')
            ->where('email', $email)
            ->get()
            ->getRowArray();

        if ($user === null) {
            $this->db->table('users')->insert([
                'name'          => 'Estudante',
                'email'         => $email,
                'password_hash' => password_hash('dataprev2026', PASSWORD_DEFAULT),
                'active'        => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
            $userId = (int) $this->db->insertID();
        } else {
            $userId = (int) $user['id'];
            // Não sobrescreve a senha em reexecuções.
            $this->db->table('users')->where('id', $userId)->update([
                'name'       => 'Estudante',
                'active'     => 1,
                'updated_at' => $now,
            ]);
        }

        $this->seedUserSettings($userId, $now);
        $this->seedStreak($userId, $now);
    }

    private function seedUserSettings(int $userId, string $now): void
    {
        $existing = $this->db->table('study_user_settings')
            ->where('user_id', $userId)
            ->get()
            ->getRowArray();

        if ($existing !== null) {
            return;
        }

        $this->db->table('study_user_settings')->insert([
            'user_id'               => $userId,
            'daily_goal_minutes'    => 60,
            'timezone'              => 'America/Fortaleza',
            'study_weekdays'        => json_encode([1, 2, 3, 4, 5]),
            'review_intervals'      => json_encode([1, 7, 30]),
            'auto_complete_tasks'   => 0,
            'notifications_enabled' => 1,
            'created_at'            => $now,
            'updated_at'            => $now,
        ]);
    }

    private function seedStreak(int $userId, string $now): void
    {
        $existing = $this->db->table('study_streaks')
            ->where('user_id', $userId)
            ->get()
            ->getRowArray();

        if ($existing !== null) {
            return;
        }

        $this->db->table('study_streaks')->insert([
            'user_id'              => $userId,
            'current_streak'       => 0,
            'best_streak'          => 0,
            'total_qualified_days' => 0,
            'last_qualified_date'  => null,
            'record_date'          => null,
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);
    }
}
