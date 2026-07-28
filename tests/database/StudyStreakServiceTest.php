<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\StudyDailyProgressModel;
use App\Models\StudyStreakHistoryModel;
use App\Models\StudyUserSettingModel;
use App\Models\UserModel;
use App\Services\Study\StudyStreakService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Cenários da seção 5 do plano: primeiro dia, dias consecutivos, final de
 * semana, ausência na sexta e retorno na segunda, quebra, múltiplas sessões,
 * edição/exclusão, datas repetidas e recorde.
 *
 * Datas de referência (2026): 03/08 segunda ... 07/08 sexta; 08-09/08 fim de semana.
 */
final class StudyStreakServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    private StudyStreakService $service;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new StudyStreakService();
        $this->userId  = (int) (new UserModel())->insert([
            'name'          => 'Teste',
            'email'         => 'teste-' . uniqid() . '@example.com',
            'password_hash' => password_hash('secret123', PASSWORD_DEFAULT),
            'active'        => 1,
        ]);

        (new StudyUserSettingModel())->insert([
            'user_id'            => $this->userId,
            'daily_goal_minutes' => 60,
            'study_weekdays'     => json_encode([1, 2, 3, 4, 5]),
            'review_intervals'   => json_encode([1, 7, 30]),
        ]);
    }

    private function qualifyDay(string $date): array
    {
        // Marca o dia como cumprido no progresso diário (como faz o ProgressService).
        $daily = new StudyDailyProgressModel();
        $row   = $daily->where('user_id', $this->userId)->where('progress_date', $date)->first();

        if ($row === null) {
            $daily->insert([
                'user_id'         => $this->userId,
                'progress_date'   => $date,
                'planned_minutes' => 60,
                'studied_minutes' => 60,
                'goal_met'        => 1,
            ]);
        } else {
            $daily->update($row['id'], ['studied_minutes' => 60, 'goal_met' => 1]);
        }

        return $this->service->registerQualifiedDay($this->userId, $date);
    }

    public function testPrimeiroDiaEstudadoIniciaOfensiva(): void
    {
        $result = $this->qualifyDay('2026-08-03');

        $this->assertTrue($result['changed']);
        $this->assertSame(1, (int) $result['streak']['current_streak']);
        $this->assertSame(1, (int) $result['streak']['best_streak']);
        $this->assertContains(StudyStreakService::EVENT_STARTED, $result['events']);
    }

    public function testDiasConsecutivosIncrementam(): void
    {
        $this->qualifyDay('2026-08-03');
        $this->qualifyDay('2026-08-04');
        $result = $this->qualifyDay('2026-08-05');

        $this->assertSame(3, (int) $result['streak']['current_streak']);
    }

    public function testFimDeSemanaNaoAumentaNemQuebra(): void
    {
        $this->qualifyDay('2026-08-06'); // quinta
        $this->qualifyDay('2026-08-07'); // sexta

        // Sábado: sessão extra não aumenta.
        $saturday = $this->qualifyDay('2026-08-08');
        $this->assertFalse($saturday['changed']);
        $this->assertSame(2, (int) $saturday['streak']['current_streak']);

        // Segunda seguinte: continua a sequência (fim de semana não quebra).
        $monday = $this->qualifyDay('2026-08-10');
        $this->assertSame(3, (int) $monday['streak']['current_streak']);
    }

    public function testAusenciaNaSextaQuebraAoRetornarNaSegunda(): void
    {
        $this->qualifyDay('2026-08-05'); // quarta
        $this->qualifyDay('2026-08-06'); // quinta
        // Sexta 07/08 não cumprida.
        $monday = $this->qualifyDay('2026-08-10');

        $this->assertContains(StudyStreakService::EVENT_BROKEN, $monday['events']);
        $this->assertSame(1, (int) $monday['streak']['current_streak']);
        $this->assertSame(2, (int) $monday['streak']['best_streak']);
    }

    public function testMultiplasSessoesNoMesmoDiaContamUmaVez(): void
    {
        $this->qualifyDay('2026-08-03');
        $again = $this->qualifyDay('2026-08-03');

        $this->assertFalse($again['changed']);
        $this->assertSame(1, (int) $again['streak']['current_streak']);
        $this->assertSame(1, (int) $again['streak']['total_qualified_days']);
    }

    public function testDataRetroativaNaoAlteraOfensiva(): void
    {
        // Mudança de fuso/horário não pode duplicar nem regredir a sequência.
        $this->qualifyDay('2026-08-04');
        $past = $this->qualifyDay('2026-08-03');

        $this->assertFalse($past['changed']);
        $this->assertSame(1, (int) $past['streak']['current_streak']);
    }

    public function testRecalculoAposExclusaoDeSessao(): void
    {
        $this->qualifyDay('2026-08-03');
        $this->qualifyDay('2026-08-04');
        $this->qualifyDay('2026-08-05');

        // "Exclusão de sessão" desqualifica a terça (04/08).
        $daily = new StudyDailyProgressModel();
        $row   = $daily->where('user_id', $this->userId)->where('progress_date', '2026-08-04')->first();
        $daily->update($row['id'], ['studied_minutes' => 10, 'goal_met' => 0]);

        $streak = $this->service->recalculate($this->userId);

        $this->assertSame(1, (int) $streak['current_streak']);
        $this->assertSame(2, (int) $streak['total_qualified_days']);
        $this->assertSame('2026-08-05', $streak['last_qualified_date']);
    }

    public function testAtualizacaoDoRecorde(): void
    {
        $this->qualifyDay('2026-08-03');
        $this->qualifyDay('2026-08-04');
        // Quebra na quarta.
        $this->qualifyDay('2026-08-07'); // sexta reinicia com 1

        $streak = $this->service->getOrCreate($this->userId);
        $this->assertSame(2, (int) $streak['best_streak']);

        // Nova sequência maior que o recorde anterior.
        $this->qualifyDay('2026-08-10');
        $this->qualifyDay('2026-08-11');
        $result = $this->qualifyDay('2026-08-12'); // 4 dias seguidos (07, 10, 11, 12)

        $this->assertSame(4, (int) $result['streak']['current_streak']);
        $this->assertSame(4, (int) $result['streak']['best_streak']);
        $this->assertSame('2026-08-12', $result['streak']['record_date']);
        $this->assertContains(StudyStreakService::EVENT_RECORD, $result['events']);
    }

    public function testHistoricoDeAlteracoesRegistrado(): void
    {
        $this->qualifyDay('2026-08-03');
        $this->qualifyDay('2026-08-04');

        $history = (new StudyStreakHistoryModel())->where('user_id', $this->userId)->findAll();

        $this->assertNotEmpty($history);
        $events = array_column($history, 'event_type');
        $this->assertContains(StudyStreakService::EVENT_STARTED, $events);
    }

    public function testEstadoEmRiscoQuandoAindaNaoEstudouHoje(): void
    {
        $this->qualifyDay('2026-08-03');

        $state = $this->service->getState($this->userId, '2026-08-04');

        $this->assertTrue($state['at_risk']);
        $this->assertSame(1, $state['current_streak']);
        $this->assertSame('Falta uma sessão para manter sua sequência.', $state['message']);
    }

    public function testEstadoQuebradoQuandoPerdeuDiaUtil(): void
    {
        $this->qualifyDay('2026-08-03');

        // Terça e quarta perdidas; consulta na quinta.
        $state = $this->service->getState($this->userId, '2026-08-06');

        $this->assertTrue($state['broken']);
        $this->assertSame(0, $state['current_streak']);
    }
}
