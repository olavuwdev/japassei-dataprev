<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

/**
 * Idempotência e integridade do seed inicial (seção 15/18 do plano):
 * executar duas vezes não pode duplicar registros; o cronograma tem 24
 * semanas e 120 tarefas úteis (segunda a sexta), cada uma com 4 checklists.
 */
final class DataprevSeederTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $seedOnce    = false;

    private function runSeeder(): void
    {
        $seeder = Database::seeder();
        $seeder->setSilent(true);
        $seeder->call('App\Database\Seeds\DataprevStudySeeder');
    }

    private function counts(): array
    {
        $db = $this->db;

        return [
            'users'     => $db->table('users')->countAllResults(),
            'exams'     => $db->table('study_exams')->countAllResults(),
            'subjects'  => $db->table('study_subjects')->countAllResults(),
            'topics'    => $db->table('study_topics')->countAllResults(),
            'columns'   => $db->table('study_kanban_columns')->countAllResults(),
            'resources' => $db->table('study_exam_resources')->countAllResults(),
            'badges'    => $db->table('study_badges')->countAllResults(),
            'plans'     => $db->table('study_plans')->countAllResults(),
            'weeks'     => $db->table('study_plan_weeks')->countAllResults(),
            'tasks'     => $db->table('study_tasks')->countAllResults(),
            'checklist' => $db->table('study_task_checklists')->countAllResults(),
        ];
    }

    public function testSeederExecutaDuasVezesSemDuplicar(): void
    {
        $this->runSeeder();
        $first = $this->counts();

        $this->runSeeder();
        $second = $this->counts();

        $this->assertSame($first, $second, 'Reexecução do seeder duplicou registros.');
    }

    public function testEstruturaDoSeed(): void
    {
        $this->runSeeder();
        $counts = $this->counts();

        $this->assertGreaterThanOrEqual(1, $counts['users']);
        $this->assertSame(1, $counts['exams'], 'Concurso DATAPREV 2026 deve estar cadastrado uma única vez.');
        $this->assertSame(16, $counts['subjects'], 'Devem existir 16 disciplinas.');
        $this->assertGreaterThanOrEqual(100, $counts['topics'], 'Tópicos do edital devem estar cadastrados.');
        $this->assertSame(6, $counts['columns'], 'Kanban deve ter 6 colunas.');
        $this->assertSame(9, $counts['resources'], 'Devem existir 9 materiais de provas antigas.');
        $this->assertSame(10, $counts['badges'], 'Devem existir 10 conquistas.');
        $this->assertSame(1, $counts['plans']);
        $this->assertSame(24, $counts['weeks'], 'O plano deve ter 24 semanas.');
        $this->assertSame(120, $counts['tasks'], 'Devem existir 120 tarefas (24 semanas x 5 dias).');
        $this->assertSame(480, $counts['checklist'], 'Cada tarefa deve ter 4 itens de checklist.');
    }

    public function testTarefasDistribuidasSomenteEmDiasUteis(): void
    {
        $this->runSeeder();

        $dates = $this->db->table('study_tasks')->select('scheduled_date')->get()->getResultArray();

        $this->assertNotEmpty($dates);

        foreach ($dates as $row) {
            $weekday = (int) date('N', strtotime($row['scheduled_date']));
            $this->assertLessThanOrEqual(5, $weekday, "Tarefa agendada para fim de semana: {$row['scheduled_date']}");
        }
    }

    public function testInicioDoPlanoEhSegundaFeira(): void
    {
        $this->runSeeder();

        $plan = $this->db->table('study_plans')->get()->getRowArray();

        $this->assertSame(1, (int) date('N', strtotime($plan['start_date'])), 'O plano deve iniciar numa segunda-feira.');
    }
}
