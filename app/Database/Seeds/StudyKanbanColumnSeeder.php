<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Cadastra as colunas iniciais do Kanban. Chave natural: code.
 */
class StudyKanbanColumnSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $columns = [
            ['code' => 'backlog',     'title' => 'Backlog',     'color' => '#6C757D', 'position' => 1, 'is_completed_column' => 0],
            ['code' => 'this_week',   'title' => 'Esta semana', 'color' => '#0D6EFD', 'position' => 2, 'is_completed_column' => 0],
            ['code' => 'today',       'title' => 'Hoje',        'color' => '#FD7E14', 'position' => 3, 'is_completed_column' => 0],
            ['code' => 'in_progress', 'title' => 'Em estudo',   'color' => '#FFC107', 'position' => 4, 'is_completed_column' => 0],
            ['code' => 'review',      'title' => 'Revisão',     'color' => '#6F42C1', 'position' => 5, 'is_completed_column' => 0],
            ['code' => 'done',        'title' => 'Concluído',   'color' => '#198754', 'position' => 6, 'is_completed_column' => 1],
        ];

        foreach ($columns as $column) {
            $existing = $this->db->table('study_kanban_columns')
                ->where('code', $column['code'])
                ->get()
                ->getRowArray();

            if ($existing === null) {
                $this->db->table('study_kanban_columns')->insert($column + [
                    'wip_limit'  => null,
                    'active'     => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                continue;
            }

            $this->db->table('study_kanban_columns')->where('id', $existing['id'])->update([
                'title'               => $column['title'],
                'color'               => $column['color'],
                'position'            => $column['position'],
                'is_completed_column' => $column['is_completed_column'],
                'active'              => 1,
                'updated_at'          => $now,
            ]);
        }
    }
}
