<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeder principal do módulo de estudos DATAPREV 2026.
 * Executa os demais seeders na ordem correta de dependências.
 *
 * Uso: php spark db:seed DataprevStudySeeder
 */
class DataprevStudySeeder extends Seeder
{
    public function run(): void
    {
        $this->call('App\Database\Seeds\UserSeeder');
        $this->call('App\Database\Seeds\StudyExamSeeder');
        $this->call('App\Database\Seeds\StudyKanbanColumnSeeder');
        $this->call('App\Database\Seeds\StudySubjectSeeder');
        $this->call('App\Database\Seeds\StudyTopicSeeder');
        $this->call('App\Database\Seeds\StudyBadgeSeeder');
        $this->call('App\Database\Seeds\StudyExamResourceSeeder');
        $this->call('App\Database\Seeds\StudyPlanSeeder');
        $this->call('App\Database\Seeds\StudyTaskSeeder');
    }
}
