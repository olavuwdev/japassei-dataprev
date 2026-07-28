<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\StudyExamModel;
use App\Models\StudyKanbanColumnModel;
use App\Models\StudyPlanModel;
use App\Models\StudySubjectModel;
use App\Models\StudyTaskChecklistModel;
use App\Models\StudyTaskModel;
use App\Models\StudyUserSettingModel;
use App\Models\UserModel;
use App\Services\Study\StudySessionService;
use App\Services\Study\StudyTaskService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;

final class StudySessionServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    private StudySessionService $service;
    private int $userId;
    private int $subjectId;
    private int $taskId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new StudySessionService();

        $this->userId = (int) (new UserModel())->insert([
            'name'          => 'Teste',
            'email'         => 'sess-' . uniqid() . '@example.com',
            'password_hash' => password_hash('secret123', PASSWORD_DEFAULT),
            'active'        => 1,
        ]);

        (new StudyUserSettingModel())->insert([
            'user_id'            => $this->userId,
            'daily_goal_minutes' => 60,
            'study_weekdays'     => json_encode([1, 2, 3, 4, 5, 6, 7]),
            'review_intervals'   => json_encode([1, 7, 30]),
        ]);

        $examId = (int) (new StudyExamModel())->insert([
            'name' => 'DATAPREV Teste', 'year' => 2026, 'profile' => 'P3', 'organizer' => 'X', 'active' => 1,
        ]);

        $this->subjectId = (int) (new StudySubjectModel())->insert([
            'exam_id' => $examId, 'name' => 'Java', 'slug' => 'java', 'category' => 'specific',
            'priority' => 3, 'sort_order' => 1, 'active' => 1,
        ]);

        $planId = (int) (new StudyPlanModel())->insert([
            'user_id' => $this->userId, 'exam_id' => $examId, 'name' => 'Plano', 'start_date' => date('Y-m-d'),
            'daily_minutes' => 60, 'weekdays' => json_encode([1, 2, 3, 4, 5]),
            'review_intervals' => json_encode([1, 7, 30]), 'active' => 1,
        ]);

        $columnId = (int) (new StudyKanbanColumnModel())->insert([
            'code' => 'today', 'title' => 'Hoje', 'position' => 1, 'is_completed_column' => 0, 'active' => 1,
        ]);
        (new StudyKanbanColumnModel())->insert([
            'code' => 'done', 'title' => 'Concluído', 'position' => 2, 'is_completed_column' => 1, 'active' => 1,
        ]);

        $this->taskId = (int) (new StudyTaskModel())->insert([
            'user_id'           => $this->userId,
            'plan_id'           => $planId,
            'subject_id'        => $this->subjectId,
            'kanban_column_id'  => $columnId,
            'title'             => 'Estudar Java',
            'task_type'         => 'theory',
            'scheduled_date'    => date('Y-m-d'),
            'estimated_minutes' => 60,
            'actual_minutes'    => 0,
            'priority'          => 3,
            'position'          => 1,
            'status'            => 'pending',
            'is_required'       => 1,
        ]);

        (new StudyTaskChecklistModel())->insert([
            'task_id' => $this->taskId, 'title' => 'Estudar teoria', 'estimated_minutes' => 25,
            'position' => 1, 'is_required' => 1, 'is_completed' => 0,
        ]);
    }

    public function testNaoPermiteDuasSessoesSimultaneas(): void
    {
        $this->service->start($this->userId, ['task_id' => $this->taskId]);

        $this->expectException(RuntimeException::class);
        $this->service->start($this->userId, ['subject_id' => $this->subjectId]);
    }

    public function testIniciarSessaoMarcaTarefaEmAndamento(): void
    {
        $session = $this->service->start($this->userId, ['task_id' => $this->taskId]);

        $this->assertSame('running', $session['status']);
        $this->assertSame('in_progress', (new StudyTaskModel())->find($this->taskId)['status']);
    }

    public function testPausarERetomar(): void
    {
        $session = $this->service->start($this->userId, ['task_id' => $this->taskId]);

        $paused = $this->service->pause($this->userId, (int) $session['id']);
        $this->assertSame('paused', $paused['status']);

        $resumed = $this->service->resume($this->userId, (int) $session['id']);
        $this->assertSame('running', $resumed['status']);
        $this->assertNotNull($resumed['last_resumed_at']);
    }

    public function testEncerramentoAtualizaTarefaEProgresso(): void
    {
        $session = $this->service->start($this->userId, ['task_id' => $this->taskId]);

        // Simula 30 minutos decorridos ajustando o início da contagem.
        (new \App\Models\StudySessionModel())->update((int) $session['id'], [
            'last_resumed_at' => date('Y-m-d H:i:s', time() - 1800),
        ]);

        $result = $this->service->finish($this->userId, (int) $session['id']);

        $this->assertSame('completed', $result['session']['status']);
        $this->assertSame(30, $result['duration_minutes']);
        $this->assertSame(30, (int) (new StudyTaskModel())->find($this->taskId)['actual_minutes']);
        $this->assertSame(30, (int) $result['daily']['studied_minutes']);
        $this->assertGreaterThanOrEqual(30, $result['xp_awarded']);
        $this->assertFalse($result['goal_met_now']);
    }

    public function testMetaCumpridaCom60Minutos(): void
    {
        $session = $this->service->start($this->userId, ['task_id' => $this->taskId]);

        (new \App\Models\StudySessionModel())->update((int) $session['id'], [
            'last_resumed_at' => date('Y-m-d H:i:s', time() - 3600),
        ]);

        $result = $this->service->finish($this->userId, (int) $session['id']);

        $this->assertTrue($result['goal_met_now']);
        $this->assertSame(1, (int) $result['daily']['goal_met']);
    }

    public function testSessaoCanceladaNaoContaMinutos(): void
    {
        $session = $this->service->start($this->userId, ['task_id' => $this->taskId]);

        $cancelled = $this->service->cancel($this->userId, (int) $session['id']);

        $this->assertSame('cancelled', $cancelled['status']);
        $this->assertSame(0, (int) (new StudyTaskModel())->find($this->taskId)['actual_minutes']);
    }

    public function testExclusaoDeSessaoReprocessaODia(): void
    {
        $session = $this->service->start($this->userId, ['task_id' => $this->taskId]);
        (new \App\Models\StudySessionModel())->update((int) $session['id'], [
            'last_resumed_at' => date('Y-m-d H:i:s', time() - 3600),
        ]);
        $result = $this->service->finish($this->userId, (int) $session['id']);
        $this->assertTrue($result['goal_met_now']);

        $this->service->delete($this->userId, (int) $session['id']);

        $daily = service('studyProgress')->getOrCreateDaily($this->userId);
        $this->assertSame(0, (int) $daily['studied_minutes']);
        $this->assertSame(0, (int) $daily['goal_met']);
    }

    public function testConclusaoDeTarefaGeraRevisoesEChecklistConta(): void
    {
        // Completa o item obrigatório do checklist e conclui a tarefa.
        $taskService = new StudyTaskService();
        $checklist   = (new StudyTaskChecklistModel())->where('task_id', $this->taskId)->first();

        $toggle = $taskService->toggleChecklistItem($this->userId, (int) $checklist['id']);
        $this->assertTrue($toggle['progress']['required_done']);
        $this->assertTrue($toggle['suggest_complete']);

        $result = $taskService->complete($this->userId, $this->taskId);

        $this->assertSame('done', $result['task']['status']);
        $this->assertCount(3, $result['reviews_created']);
        $this->assertSame(100, $result['task']['checklist_progress']['percent']);
    }
}
