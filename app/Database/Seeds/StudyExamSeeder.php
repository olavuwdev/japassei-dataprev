<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Cadastra o concurso DATAPREV 2026. Chave natural: name + year.
 */
class StudyExamSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $data = [
            'name'          => 'DATAPREV 2026',
            'year'          => 2026,
            'profile'       => 'Perfil 3: Desenvolvimento de Software',
            'organizer'     => 'A definir',
            'exam_date'     => null,
            'daily_minutes' => 60,
            'active'        => 1,
        ];

        $existing = $this->db->table('study_exams')
            ->where('name', $data['name'])
            ->where('year', $data['year'])
            ->get()
            ->getRowArray();

        if ($existing === null) {
            $this->db->table('study_exams')->insert(
                $data + ['created_at' => $now, 'updated_at' => $now]
            );

            return;
        }

        $this->db->table('study_exams')->where('id', $existing['id'])->update([
            'profile'       => $data['profile'],
            'organizer'     => $data['organizer'],
            'daily_minutes' => $data['daily_minutes'],
            'active'        => 1,
            'updated_at'    => $now,
        ]);
    }
}
