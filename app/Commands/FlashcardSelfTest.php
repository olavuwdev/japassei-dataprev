<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\FlashcardModel;
use App\Models\FlashcardStateModel;
use App\Models\UserModel;
use App\Services\Flashcard\FlashcardApiTokenService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Throwable;

/**
 * Verificação ponta a ponta do módulo de flashcards: criação, agendamento pelo
 * FSRS, revisão idempotente, desfazer e importação externa.
 *
 * Cria e remove os próprios dados. Use apenas em desenvolvimento/homologação.
 */
class FlashcardSelfTest extends BaseCommand
{
    protected $group       = 'Flashcards';
    protected $name        = 'flashcards:self-test';
    protected $description = 'Executa uma verificação ponta a ponta do módulo (cria e remove dados de teste).';
    protected $usage       = 'flashcards:self-test [--user 1] [--keep]';
    protected $options     = [
        '--user' => 'ID do usuário usado no teste (padrão: primeiro usuário ativo).',
        '--keep' => 'Não remove os dados criados.',
    ];

    private int $passed = 0;
    private int $failed = 0;

    public function run(array $params): int
    {
        if (ENVIRONMENT === 'production') {
            CLI::error('Este comando não deve ser executado em produção.');

            return EXIT_ERROR;
        }

        $userId = (int) ($params['user'] ?? CLI::getOption('user') ?? 0);

        if ($userId === 0) {
            $user = (new UserModel())->where('active', 1)->orderBy('id', 'ASC')->first();

            if ($user === null) {
                CLI::error('Nenhum usuário cadastrado. Rode os seeders primeiro.');

                return EXIT_ERROR;
            }

            $userId = (int) $user['id'];
        }

        CLI::write('Usuário de teste: #' . $userId, 'yellow');
        CLI::newLine();

        $created = [];

        try {
            $created = $this->runChecks($userId);
        } catch (Throwable $e) {
            $this->fail('Exceção inesperada: ' . $e->getMessage());
            CLI::write($e->getTraceAsString(), 'dark_gray');
        } finally {
            if (! CLI::getOption('keep')) {
                $this->cleanup($userId, $created);
            }
        }

        CLI::newLine();
        CLI::write('Aprovados: ' . $this->passed . ' · Falhas: ' . $this->failed, $this->failed === 0 ? 'green' : 'red');

        return $this->failed === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    /**
     * @return array<string, mixed> referências criadas, para limpeza
     */
    private function runChecks(int $userId): array
    {
        $created = ['cards' => [], 'tokens' => [], 'imports' => []];

        // --------------------------------------------------- serviço FSRS
        CLI::write('— Serviço FSRS', 'cyan');
        $health = service('fsrs')->health();
        $this->check('serviço respondendo', $health['online'], (string) ($health['message'] ?? ''));

        if (! $health['online']) {
            CLI::write('  Sem o serviço FSRS as demais verificações de agendamento não podem rodar.', 'yellow');

            return $created;
        }

        CLI::write('  ts-fsrs ' . $health['version'], 'dark_gray');

        // ------------------------------------------------- cartão manual
        CLI::write('— Criação de cartões', 'cyan');

        $marker = 'SELFTEST-' . bin2hex(random_bytes(4));

        $result = service('flashcard')->createNote($userId, [
            'card_type' => 'basic',
            'question'  => $marker . ' Qual princípio exige a divulgação dos atos administrativos?',
            'answer'    => 'Princípio da publicidade.',
            'tags'      => ['selftest'],
        ]);

        $this->check('cartão básico criado', count($result['cards']) === 1);
        $cardId = (int) ($result['cards'][0]['id'] ?? 0);
        $created['cards'][] = $cardId;

        $duplicate = service('flashcard')->createNote($userId, [
            'card_type' => 'basic',
            'question'  => $marker . ' Qual princípio exige a divulgação dos atos administrativos?',
            'answer'    => 'Princípio da publicidade.',
        ]);
        $this->check('duplicidade exata bloqueada', $duplicate['cards'] === [] && $duplicate['duplicates'] !== []);

        $cloze = service('flashcard')->createNote($userId, [
            'card_type' => 'cloze',
            'question'  => $marker . ' A {{c1::legalidade}} exige lei; a {{c2::publicidade}} exige divulgação.',
        ]);
        $this->check('cloze com 2 lacunas gera 2 cartões', count($cloze['cards']) === 2);
        foreach ($cloze['cards'] as $c) { $created['cards'][] = (int) $c['id']; }

        $invalid = service('flashcard')->createNote($userId, ['card_type' => 'basic', 'question' => '', 'answer' => '']);
        $this->check('cartão sem pergunta é rejeitado', $invalid['errors'] !== []);

        $badCloze = service('flashcard')->createNote($userId, ['card_type' => 'cloze', 'question' => 'Texto sem lacuna nenhuma.']);
        $this->check('cloze sem marcação é rejeitado', $badCloze['errors'] !== []);

        // ------------------------------------------------- estado inicial
        CLI::write('— Estado inicial no FSRS', 'cyan');

        $stateModel = new FlashcardStateModel();
        $state = $stateModel->where('flashcard_id', $cardId)->where('user_id', $userId)->first();

        $this->check('estado criado como Novo', $state !== null && (int) $state['state'] === 0);
        $this->check('sem revisões nem esquecimentos', (int) $state['reps'] === 0 && (int) $state['lapses'] === 0);
        $this->check('entra na fila de novos', (int) $state['in_queue'] === 1);

        // ------------------------------------------------- sanitização
        CLI::write('— Sanitização de HTML', 'cyan');

        $xss = service('flashcardValidation')->sanitizeHtml('<p>ok</p><script>alert(1)</script><img src=x onerror="alert(2)">');
        $this->check('script removido', ! str_contains($xss, '<script'));
        $this->check('atributo de evento removido', ! str_contains(strtolower($xss), 'onerror'));

        // ------------------------------------------------- fila e sessão
        CLI::write('— Sessão de revisão', 'cyan');

        $session = service('flashcardSession')->start($userId);
        $this->check('sessão criada', ! empty($session['uuid']));

        $next = service('flashcardSession')->next($userId, $session['uuid']);
        $this->check('cartão entregue', $next['card'] !== null);
        $this->check('quatro intervalos previstos', count($next['card']['intervals'] ?? []) === 4);

        if ($next['card'] !== null) {
            CLI::write('  intervalos: ' . implode(' · ', $next['card']['intervals']), 'dark_gray');
        }

        // ------------------------------------------------- avaliação
        $target = $next['card']['id'];
        $requestUuid = service('flashcardSession')->uuid();

        $review = service('flashcardSession')->review(
            $userId,
            $session['uuid'],
            $target,
            3,
            $requestUuid,
            $next['card']['state_version']
        );

        $this->check('avaliação registrada', ! $review['duplicate'] && (int) $review['state']['reps'] === 1);
        $this->check('estado avançou para Aprendendo', (int) $review['state']['state'] === 1);
        $this->check('data futura definida pelo FSRS', strtotime((string) $review['state']['due'] . ' UTC') > time());

        // ------------------------------------------------- idempotência
        $again = service('flashcardSession')->review($userId, $session['uuid'], $target, 3, $requestUuid);
        $this->check('requisição repetida não duplica revisão', $again['duplicate'] === true);

        $count = Database::connect()->table('study_flashcard_reviews')
            ->where('uuid', $requestUuid)->countAllResults();
        $this->check('somente um log gravado', $count === 1);

        // ------------------------------------------------- concorrência
        try {
            service('flashcardSession')->review(
                $userId,
                $session['uuid'],
                $target,
                3,
                service('flashcardSession')->uuid(),
                1 // versão desatualizada de propósito
            );
            $this->fail('versão desatualizada deveria ser rejeitada');
        } catch (Throwable $e) {
            $this->check('bloqueio otimista rejeita versão antiga', str_contains($e->getMessage(), 'outra aba'));
        }

        // ------------------------------------------------- desfazer
        CLI::write('— Desfazer', 'cyan');

        $restored = service('flashcardSession')->undo($userId, $session['uuid']);
        $this->check('estado restaurado para Novo', (int) $restored['state'] === 0 && (int) $restored['reps'] === 0);

        $undone = Database::connect()->table('study_flashcard_reviews')
            ->where('uuid', $requestUuid)->get()->getRowArray();
        $this->check('log marcado como desfeito, não apagado', $undone !== null && (int) $undone['undone'] === 1);

        // ------------------------------------------------- cartão suspenso
        CLI::write('— Suspensão', 'cyan');

        service('flashcard')->toggleSuspend($userId, $cardId, true);
        $queue = service('flashcardQueue')->build($userId);
        $ids = array_column($queue['cards'], 'id');
        $this->check('cartão suspenso sai da fila', ! in_array($cardId, array_map('intval', $ids), true));
        service('flashcard')->toggleSuspend($userId, $cardId, false);

        // ------------------------------------------------- encerramento
        $summary = service('flashcardSession')->finish($userId, $session['uuid']);
        $this->check('resumo da sessão gerado', isset($summary['reviewed'], $summary['remaining']));

        // ------------------------------------------------- SSRF
        CLI::write('— Proteção contra SSRF', 'cyan');

        $extractor = new \App\Services\Flashcard\ContentExtractorService();

        foreach (['http://localhost/x', 'http://127.0.0.1/x', 'http://169.254.169.254/latest/meta-data', 'ftp://exemplo.com/x', 'http://192.168.0.1/'] as $blocked) {
            try {
                $extractor->assertSafeUrl($blocked);
                $this->fail('deveria bloquear ' . $blocked);
            } catch (Throwable $e) {
                $this->check('bloqueia ' . $blocked, true);
            }
        }

        // ------------------------------------------------- token da API
        CLI::write('— API externa', 'cyan');

        $tokenService = new FlashcardApiTokenService();
        $token = $tokenService->create($userId, 'Self-test ' . $marker, [FlashcardApiTokenService::SCOPE_IMPORT], false);
        $created['tokens'][] = (int) $token['record']['id'];

        $this->check('token gerado com prefixo correto', str_starts_with($token['token'], 'flc_live_'));
        $this->check('token não é armazenado em texto puro', $token['record']['token_hash'] !== $token['token']);
        $this->check('token válido é aceito', $tokenService->verify($token['token']) !== null);
        $this->check('token inválido é recusado', $tokenService->verify('flc_live_invalido') === null);
        $this->check('escopo de importação cobre criação', $tokenService->hasScope($token['record'], FlashcardApiTokenService::SCOPE_CREATE));

        // ------------------------------------------------- importação
        $payload = [
            'external_id' => 'selftest-' . $marker,
            'source'      => ['provider' => 'selftest', 'title' => 'Lote de teste'],
            'discipline'  => ['name' => 'Direito Administrativo', 'create_if_not_exists' => true],
            'subject'     => ['name' => 'Princípios ' . $marker, 'create_if_not_exists' => true],
            'settings'    => ['send_to_review' => true, 'requires_approval' => false, 'prevent_duplicates' => true],
            'cards'       => [
                ['external_id' => $marker . '-1', 'type' => 'basic', 'question' => $marker . ' Pergunta importada um?', 'answer' => 'Resposta um.'],
                ['external_id' => $marker . '-2', 'type' => 'cloze', 'text' => $marker . ' A {{c1::publicidade}} exige divulgação.'],
                ['external_id' => $marker . '-3', 'type' => 'basic', 'question' => $marker . ' Sem resposta?'],
            ],
        ];

        $import = service('flashcardImport')->import($userId, $payload, [
            'token_id'          => (int) $token['record']['id'],
            'requires_approval' => false,
            'idempotency_key'   => 'selftest-key-' . $marker,
        ]);

        $created['imports'][] = $import['body']['import_id'] ?? null;

        $this->check('importação parcial devolve 207', $import['status'] === 207, 'status ' . $import['status']);
        $this->check('cartão sem resposta é rejeitado', ($import['body']['summary']['rejected'] ?? 0) === 1);
        $this->check('demais cartões são criados', ($import['body']['summary']['created'] ?? 0) >= 2);
        $this->check('disciplina reutilizada ou criada', ! empty($import['body']['discipline']['id']));
        $this->check('erro detalhado por cartão', ! empty($import['body']['errors'][0]['code']));

        $importedIds = array_filter(array_column($import['body']['cards'] ?? [], 'id'));
        foreach ($importedIds as $id) { $created['cards'][] = (int) $id; }

        // Cartões importados começam como Novo, sem data fixa.
        if ($importedIds !== []) {
            $importedState = $stateModel->where('flashcard_id', (int) reset($importedIds))->where('user_id', $userId)->first();
            $this->check('cartão importado inicia como Novo', (int) $importedState['state'] === 0);
            $this->check('cartão importado entra na fila', (int) $importedState['in_queue'] === 1);
        }

        // Idempotência do lote.
        $repeat = service('flashcardImport')->import($userId, $payload, [
            'token_id'        => (int) $token['record']['id'],
            'idempotency_key' => 'selftest-key-' . $marker,
        ]);
        $this->check('lote repetido devolve 409', $repeat['status'] === 409, 'status ' . $repeat['status']);

        // external_id do cartão evita recadastro.
        $second = service('flashcardImport')->import($userId, array_merge($payload, [
            'external_id' => 'selftest-outro-' . $marker,
            'cards'       => [$payload['cards'][0]],
        ]), ['token_id' => (int) $token['record']['id'], 'idempotency_key' => 'outra-key-' . $marker]);

        $this->check('cartão com external_id repetido é duplicata', ($second['body']['summary']['duplicates'] ?? 0) === 1);

        // Taxonomia insensível a caixa e acento.
        $third = service('flashcardImport')->import($userId, [
            'discipline' => ['name' => 'direito administrativo', 'create_if_not_exists' => false],
            'cards'      => [['question' => $marker . ' Pergunta com disciplina em minúsculas?', 'answer' => 'Sim.']],
        ], ['token_id' => (int) $token['record']['id'], 'idempotency_key' => 'terceira-key-' . $marker]);

        $this->check(
            'disciplina existente reutilizada apesar da caixa',
            in_array($third['status'], [200, 201], true),
            'status ' . $third['status'] . ' — ' . ($third['body']['message'] ?? '')
        );

        foreach (array_filter(array_column($third['body']['cards'] ?? [], 'id')) as $id) {
            $created['cards'][] = (int) $id;
        }

        // Taxonomia inexistente sem autorização → 404.
        $missing = service('flashcardImport')->import($userId, [
            'discipline' => ['name' => 'Disciplina Inexistente ' . $marker, 'create_if_not_exists' => false],
            'cards'      => [['question' => 'X?', 'answer' => 'Y.']],
        ], ['token_id' => (int) $token['record']['id'], 'idempotency_key' => 'quarta-key-' . $marker]);

        $this->check('disciplina inexistente sem permissão devolve 404', $missing['status'] === 404, 'status ' . $missing['status']);

        // ------------------------------------------------- estatísticas
        CLI::write('— Estatísticas', 'cyan');

        $stats = service('flashcardStatistics');
        $summaryStats = $stats->dailySummary($userId);
        $this->check('resumo diário calculado', isset($summaryStats['total_cards'], $summaryStats['due_reviews']));
        $this->check('previsão de carga calculada', isset($stats->forecast($userId)['week']));
        $this->check('desempenho por disciplina calculado', is_array($stats->bySubject($userId)));
        $this->check('histórico paginado', isset($stats->reviewHistory($userId)['items']));

        return $created;
    }

    /**
     * @param array<string, mixed> $created
     */
    private function cleanup(int $userId, array $created): void
    {
        $db = Database::connect();

        $cardIds = array_filter(array_map('intval', $created['cards'] ?? []));

        if ($cardIds !== []) {
            $noteIds = $db->table('study_flashcards')->select('note_id')->whereIn('id', $cardIds)->get()->getResultArray();

            $db->table('study_flashcard_reviews')->whereIn('flashcard_id', $cardIds)->delete();
            $db->table('study_flashcard_states')->whereIn('flashcard_id', $cardIds)->delete();
            $db->table('study_flashcards')->whereIn('id', $cardIds)->delete();

            $notes = array_filter(array_column($noteIds, 'note_id'));
            if ($notes !== []) {
                $db->table('study_flashcard_notes')->whereIn('id', $notes)->delete();
            }
        }

        foreach (($created['tokens'] ?? []) as $tokenId) {
            $db->table('study_flashcard_imports')->where('token_id', (int) $tokenId)->delete();
            $db->table('study_flashcard_api_tokens')->where('id', (int) $tokenId)->delete();
        }

        $db->table('study_flashcard_sessions')->where('user_id', $userId)
            ->where('created_at >=', gmdate('Y-m-d H:i:s', time() - 3600))->delete();

        CLI::newLine();
        CLI::write('Dados de teste removidos.', 'dark_gray');
    }

    private function check(string $label, bool $condition, string $detail = ''): void
    {
        if ($condition) {
            $this->passed++;
            CLI::write('  ✓ ' . $label, 'green');

            return;
        }

        $this->failed++;
        CLI::write('  ✗ ' . $label . ($detail !== '' ? ' (' . $detail . ')' : ''), 'red');
    }

    private function fail(string $label): void
    {
        $this->failed++;
        CLI::write('  ✗ ' . $label, 'red');
    }
}
