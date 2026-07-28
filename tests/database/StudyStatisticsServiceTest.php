<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\StudyExamModel;
use App\Models\StudySubjectModel;
use App\Models\StudyUserSettingModel;
use App\Models\UserModel;
use App\Services\Study\StudyStatisticsService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;

final class StudyStatisticsServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    private StudyStatisticsService $service;
    private int $userId;
    private int $subjectId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new StudyStatisticsService();

        $this->userId = (int) (new UserModel())->insert([
            'name'          => 'Teste',
            'email'         => 'stats-' . uniqid() . '@example.com',
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
            'exam_id' => $examId, 'name' => 'Banco de Dados', 'slug' => 'banco-de-dados',
            'category' => 'specific', 'priority' => 3, 'sort_order' => 1, 'active' => 1,
        ]);
    }

    public function testSomaDeAcertosErrosBrancosNaoPodeExcederTotal(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/não pode ser maior/');

        $this->service->registerAttempt($this->userId, [
            'subject_id'        => $this->subjectId,
            'attempt_date'      => '2026-08-03',
            'questions_total'   => 10,
            'questions_correct' => 6,
            'questions_wrong'   => 4,
            'questions_blank'   => 2,
        ]);
    }

    public function testPercentualDeAcertosCalculadoNoBackend(): void
    {
        $result = $this->service->registerAttempt($this->userId, [
            'subject_id'        => $this->subjectId,
            'attempt_date'      => '2026-08-03',
            'questions_total'   => 20,
            'questions_correct' => 15,
            'questions_wrong'   => 4,
            'questions_blank'   => 1,
        ]);

        $this->assertSame(75.0, (float) $result['attempt']['score_percentage']);
    }

    public function testXpBonusComAproveitamentoAcimaDe80PorCento(): void
    {
        $result = $this->service->registerAttempt($this->userId, [
            'subject_id'        => $this->subjectId,
            'attempt_date'      => '2026-08-03',
            'questions_total'   => 10,
            'questions_correct' => 9,
            'questions_wrong'   => 1,
            'questions_blank'   => 0,
        ]);

        $this->assertSame(5, $result['xp_awarded']);
    }

    public function testTotalZeroRejeitado(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service->registerAttempt($this->userId, [
            'subject_id'        => $this->subjectId,
            'questions_total'   => 0,
            'questions_correct' => 0,
            'questions_wrong'   => 0,
            'questions_blank'   => 0,
        ]);
    }

    public function testUsuarioNaoEditaRegistroDeOutro(): void
    {
        $result = $this->service->registerAttempt($this->userId, [
            'subject_id'        => $this->subjectId,
            'attempt_date'      => '2026-08-03',
            'questions_total'   => 10,
            'questions_correct' => 5,
            'questions_wrong'   => 5,
            'questions_blank'   => 0,
        ]);

        $otherUser = (int) (new UserModel())->insert([
            'name'          => 'Outro',
            'email'         => 'outro-' . uniqid() . '@example.com',
            'password_hash' => password_hash('secret123', PASSWORD_DEFAULT),
            'active'        => 1,
        ]);

        $this->expectException(RuntimeException::class);
        $this->service->updateAttempt($otherUser, (int) $result['attempt']['id'], ['questions_correct' => 10]);
    }
}
