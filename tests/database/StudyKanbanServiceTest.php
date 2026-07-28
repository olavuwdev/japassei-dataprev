<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\StudyExamModel;
use App\Models\StudyKanbanColumnModel;
use App\Models\StudyPlanModel;
use App\Models\StudySubjectModel;
use App\Models\StudyTaskModel;
use App\Models\StudyTaskStatusHistoryModel;
use App\Models\StudyUserSettingModel;
use App\Models\UserModel;
use App\Services\Study\StudyKanbanService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;

final class StudyKanbanServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    private StudyKanbanService $service;
    private int $userId;
    private int $planId;
    private int $subjectId;
    private int $backlogId;
    private int $todayId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new StudyKanbanService();

        $this->userId = (int) (new UserModel())->insert([
            'name'          => 'Teste',
            'email'         => 'kanban-' . uniqid() . '@example.com',
            'password_hash' => password_hash('secret123', PASSWORD_DEFAULT),
            'active'        => 1,
        ]);

        (new StudyUserSettingModel())->insert([
            'user_id'          => $this->userId,
            'study_weekdays'   => json_encode([1, 2, 3, 4, 5]),
            'review_intervals' => json_encode([1, 7, 30]),
        ]);

        $examId = (int) (new StudyExamModel())->insert([
            'name' => 'DATAPREV Teste', 'year' => 2026, 'profile' => 'P3', 'organizer' => 'X', 'active' => 1,
        ]);

        $this->subjectId = (int) (new StudySubjectModel())->insert([
            'exam_id' => $examId, 'name' => 'Java', 'slug' => 'java', 'category' => 'specific',
            'priority' => 3, 'sort_order' => 1, 'active' => 1,
        ]);

        $this->planId = (int) (new StudyPlanModel())->insert([
            'user_id' => $this->userId, 'exam_id' => $examId, 'name' => 'Plano', 'start_date' => '2026-08-03',
            'daily_minutes' => 60, 'weekdays' => json_encode([1, 2, 3, 4, 5]),
            'review_intervals' => json_encode([1, 7, 30]), 'active' => 1,
        ]);

        $columns = new StudyKanbanColumnModel();
        $this->backlogId = (int) $columns->insert(['code' => 'backlog', 'title' => 'Backlog', 'position' => 1, 'is_completed_column' => 0, 'active' => 1]);
        $this->todayId   = (int) $columns->insert(['code' => 'today', 'title' => 'Hoje', 'position' => 2, 'is_completed_column' => 0, 'active' => 1]);
        $columns->insert(['code' => 'done', 'title' => 'Concluído', 'position' => 3, 'is_completed_column' => 1, 'active' => 1]);
    }

    private function makeTask(string $title, int $columnId, int $position, ?int $userId = null): int
    {
        return (int) (new StudyTaskModel())->insert([
            'user_id'           => $userId ?? $this->userId,
            'plan_id'           => $this->planId,
            'subject_id'        => $this->subjectId,
            'kanban_column_id'  => $columnId,
            'title'             => $title,
            'task_type'         => 'theory',
            'estimated_minutes' => 60,
            'actual_minutes'    => 0,
            'priority'          => 3,
            'position'          => $position,
            'status'            => 'pending',
            'is_required'       => 1,
            'scheduled_date'    => '2026-08-03',
        ]);
    }

    public function testMoverCardPersisteColunaEPosicao(): void
    {
        $taskA = $this->makeTask('A', $this->backlogId, 1);
        $this->makeTask('B', $this->todayId, 1);

        $result = $this->service->moveCard($this->userId, $taskA, $this->todayId, 1);

        $this->assertSame($this->todayId, (int) $result['task']['kanban_column_id']);
        $this->assertSame(1, (int) $result['task']['position']);
    }

    public function testPosicoesNaoDuplicamNaColunaDestino(): void
    {
        $taskA = $this->makeTask('A', $this->backlogId, 1);
        $taskB = $this->makeTask('B', $this->todayId, 1);
        $taskC = $this->makeTask('C', $this->todayId, 2);

        $this->service->moveCard($this->userId, $taskA, $this->todayId, 1);

        $positions = (new StudyTaskModel())
            ->where('kanban_column_id', $this->todayId)
            ->orderBy('position', 'ASC')
            ->findAll();

        $values = array_map(static fn (array $t): int => (int) $t['position'], $positions);

        $this->assertSame([1, 2, 3], $values);
        $this->assertCount(count($values), array_unique($values), 'Posições duplicadas na mesma coluna.');

        $byTitle = array_column($positions, 'position', 'title');
        $this->assertSame(1, (int) $byTitle['A']);
        $this->assertSame(2, (int) $byTitle['B']);
        $this->assertSame(3, (int) $byTitle['C']);
    }

    public function testMovimentacaoRegistraHistorico(): void
    {
        $taskA = $this->makeTask('A', $this->backlogId, 1);

        $this->service->moveCard($this->userId, $taskA, $this->todayId, 1);

        $history = (new StudyTaskStatusHistoryModel())->where('task_id', $taskA)->findAll();

        $this->assertCount(1, $history);
        $this->assertSame($this->backlogId, (int) $history[0]['from_column_id']);
        $this->assertSame($this->todayId, (int) $history[0]['to_column_id']);
    }

    public function testMoverParaColunaDoneConcluiTarefa(): void
    {
        $taskA  = $this->makeTask('A', $this->backlogId, 1);
        $doneId = (int) (new StudyKanbanColumnModel())->where('code', 'done')->first()['id'];

        $result = $this->service->moveCard($this->userId, $taskA, $doneId, 1);

        $this->assertTrue($result['completed']);
        $this->assertSame('done', $result['task']['status']);
        $this->assertNotNull($result['task']['completed_at']);
    }

    public function testUsuarioNaoMoveCardDeOutroUsuario(): void
    {
        $otherUser = (int) (new UserModel())->insert([
            'name'          => 'Outro',
            'email'         => 'outro-' . uniqid() . '@example.com',
            'password_hash' => password_hash('secret123', PASSWORD_DEFAULT),
            'active'        => 1,
        ]);

        $task = $this->makeTask('Alheia', $this->backlogId, 1);

        $this->expectException(RuntimeException::class);
        $this->service->moveCard($otherUser, $task, $this->todayId, 1);
    }
}
