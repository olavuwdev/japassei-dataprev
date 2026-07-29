<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeder para criar o usuário ollavoadriel@gmail.com com senha 1.
 * Idempotente - não sobrescreve se o usuário já existe.
 */
class AddOlavoadrielUserSeeder extends Seeder
{
    public function run(): void
    {
        $now   = date('Y-m-d H:i:s');
        $email = 'ollavoadriel@gmail.com';

        $user = $this->db->table('users')
            ->where('email', $email)
            ->get()
            ->getRowArray();

        if ($user === null) {
            // Criar novo usuário
            $this->db->table('users')->insert([
                'name'          => 'Olavo Dev',
                'email'         => $email,
                'password_hash' => password_hash('1', PASSWORD_DEFAULT),
                'active'        => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
            $userId = (int) $this->db->insertID();

            echo "\n✅ Usuário criado: {$email} (ID: {$userId})\n";
        } else {
            $userId = (int) $user['id'];
            // Não sobrescreve a senha em reexecuções
            $this->db->table('users')->where('id', $userId)->update([
                'name'       => 'Olavo Dev',
                'active'     => 1,
                'updated_at' => $now,
            ]);

            echo "\n⚠️  Usuário já existe: {$email} (ID: {$userId})\n";
        }

        // Criar settings se não existirem
        $this->seedUserSettings($userId, $now);

        // Criar streak se não existir
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

        echo "  • Configurações de estudo criadas\n";
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

        echo "  • Registro de streak criado\n";
    }
}
