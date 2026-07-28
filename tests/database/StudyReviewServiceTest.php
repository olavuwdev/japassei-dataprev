<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\StudyExamModel;
use App\Models\StudyReviewModel;
use App\Models\StudySubjectModel;
use App\Models\StudyTopicModel;
use App\Models\StudyUserSettingModel;
use App\Models\UserModel;
use App\Services\Study\StudyReviewService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;

final class StudyReviewServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    private StudyReviewService $service;
    private int $userId;
    private int $subjectId;
    private int $topicId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new StudyReviewService();

        $this->userId = (int) (new UserModel())->insert([
            'name'          => 'Teste',
            'email'         => 'rev-' . uniqid() . '@example.com',
            'password_hash' => password_hash('secret123', PASSWORD_DEFAULT),
            'active'        => 1,
        ]);

        (new StudyUserSettingModel())->insert([
            'user_id'          => $this->userId,
            'review_intervals' => json_encode([1, 7, 30]),
            'study_weekdays'   => json_encode([1, 2, 3, 4, 5]),
        ]);

        $examId = (int) (new StudyExamModel())->insert([
            'name' => 'DATAPREV Teste', 'year' => 2026, 'profile' => 'P3', 'organizer' => 'X', 'active' => 1,
        ]);

        $this->subjectId = (int) (new StudySubjectModel())->insert([
            'exam_id' => $examId, 'name' => 'Java', 'slug' => 'java', 'category' => 'specific',
            'priority' => 3, 'sort_order' => 1, 'active' => 1,
        ]);

        $this->topicId = (int) (new StudyTopicModel())->insert([
            'subject_id' => $this->subjectId, 'name' => 'OO', 'slug' => 'oo',
            'estimated_minutes' => 60, 'difficulty' => 2, 'sort_order' => 1, 'active' => 1,
        ]);
    }

    private function makeTask(): array
    {
        return [
            'id'         => 123,
            'task_type'  => 'theory',
            'subject_id' => $this->subjectId,
            'topic_id'   => $this->topicId,
        ];
    }

    public function testGeraRevisoesDe1e7e30Dias(): void
    {
        $created = $this->service->generateForTask($this->userId, $this->makeTask(), '2026-08-03');

        $this->assertCount(3, $created);
        $this->assertSame([1, 7, 30], array_map(static fn (array $r): int => (int) $r['interval_days'], $created));
        $this->assertSame(['2026-08-04', '2026-08-10', '2026-09-02'], array_column($created, 'due_date'));
        $this->assertSame([1, 2, 3], array_map(static fn (array $r): int => (int) $r['review_number'], $created));
    }

    public function testNaoDuplicaRevisoesParaMesmoConteudo(): void
    {
        $this->service->generateForTask($this->userId, $this->makeTask(), '2026-08-03');
        $again = $this->service->generateForTask($this->userId, $this->makeTask(), '2026-08-04');

        $this->assertSame([], $again);
        $this->assertSame(3, (new StudyReviewModel())->where('user_id', $this->userId)->countAllResults());
    }

    public function testNaoGeraParaTarefaNaoTeorica(): void
    {
        $task              = $this->makeTask();
        $task['task_type'] = 'questions';

        $this->assertSame([], $this->service->generateForTask($this->userId, $task, '2026-08-03'));
    }

    public function testConcluirValidaAcertosMaiorQueTotal(): void
    {
        $created = $this->service->generateForTask($this->userId, $this->makeTask(), '2026-08-03');

        $this->expectException(RuntimeException::class);
        $this->service->complete($this->userId, (int) $created[0]['id'], [
            'questions_total'   => 5,
            'questions_correct' => 8,
        ]);
    }

    public function testConcluirRegistraDados(): void
    {
        $created = $this->service->generateForTask($this->userId, $this->makeTask(), '2026-08-03');

        $result = $this->service->complete($this->userId, (int) $created[0]['id'], [
            'questions_total'   => 10,
            'questions_correct' => 9,
            'difficulty'        => 2,
            'notes'             => 'Boa revisão',
        ]);

        $this->assertSame(StudyReviewService::STATUS_COMPLETED, $result['review']['status']);
        $this->assertGreaterThanOrEqual(5, $result['xp_awarded']);
    }

    public function testUsuarioNaoAcessaRevisaoDeOutro(): void
    {
        $created = $this->service->generateForTask($this->userId, $this->makeTask(), '2026-08-03');

        $otherUser = (int) (new UserModel())->insert([
            'name'          => 'Outro',
            'email'         => 'outro-' . uniqid() . '@example.com',
            'password_hash' => password_hash('secret123', PASSWORD_DEFAULT),
            'active'        => 1,
        ]);

        $this->expectException(RuntimeException::class);
        $this->service->complete($otherUser, (int) $created[0]['id'], ['questions_total' => 1, 'questions_correct' => 1]);
    }
}
