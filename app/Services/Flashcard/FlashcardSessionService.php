<?php

declare(strict_types=1);

namespace App\Services\Flashcard;

use App\Models\FlashcardModel;
use App\Models\FlashcardReviewLogModel;
use App\Models\FlashcardSessionModel;
use App\Models\FlashcardSettingModel;
use App\Models\FlashcardStateModel;
use Config\Database;
use RuntimeException;
use Throwable;

/**
 * Ciclo completo de uma sessão de revisão: criação, entrega dos cartões,
 * registro das avaliações, desfazer e encerramento.
 */
class FlashcardSessionService
{
    public function __construct(
        private ?FlashcardQueueService $queue = null,
        private ?FsrsClientService $fsrs = null,
        private ?FlashcardValidationService $validator = null,
        private ?FlashcardAuditService $audit = null,
    ) {
        $this->queue     ??= new FlashcardQueueService();
        $this->fsrs      ??= new FsrsClientService();
        $this->validator ??= new FlashcardValidationService();
        $this->audit     ??= new FlashcardAuditService();
    }

    // ------------------------------------------------------------- Sessão

    /**
     * Cria a sessão e calcula o total previsto.
     *
     * @param array{subject_id?:?int, topic_id?:?int} $filters
     *
     * @return array<string, mixed>
     */
    public function start(int $userId, array $filters = []): array
    {
        $model = new FlashcardSessionModel();

        // Encerra sessões antigas abandonadas para não poluir o histórico.
        $model->where('user_id', $userId)
            ->whereIn('status', [FlashcardSessionModel::STATUS_ACTIVE, FlashcardSessionModel::STATUS_PAUSED])
            ->set(['status' => FlashcardSessionModel::STATUS_ABANDONED, 'finished_at' => gmdate('Y-m-d H:i:s')])
            ->update();

        $counts = $this->queue->counts($userId, $filters);

        $id = (int) $model->insert([
            'uuid'          => $this->uuid(),
            'user_id'       => $userId,
            'subject_id'    => $filters['subject_id'] ?? null,
            'topic_id'      => $filters['topic_id'] ?? null,
            'status'        => FlashcardSessionModel::STATUS_ACTIVE,
            'planned_total' => $counts['total'],
            'new_total'     => $counts['new'],
            'started_at'    => gmdate('Y-m-d H:i:s'),
        ], true);

        return $model->find($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function findSession(int $userId, string $uuid): array
    {
        $session = (new FlashcardSessionModel())->where('user_id', $userId)->where('uuid', $uuid)->first();

        if ($session === null) {
            throw new RuntimeException('Sessão não encontrada.');
        }

        return $session;
    }

    /**
     * Próximo cartão da sessão, já com a prévia dos quatro intervalos.
     *
     * @return array{card:?array<string,mixed>, counts:array<string,int>, progress:array<string,int>, next_due_in_seconds:?int}
     */
    public function next(int $userId, string $sessionUuid): array
    {
        $session = $this->findSession($userId, $sessionUuid);
        $filters = ['subject_id' => $session['subject_id'], 'topic_id' => $session['topic_id']];

        $queue  = $this->queue->build($userId, $filters);
        $counts = $queue['counts'];

        $progress = [
            'reviewed' => (int) $session['reviewed_total'],
            'planned'  => max((int) $session['planned_total'], (int) $session['reviewed_total'] + $counts['new'] + $counts['learning'] + $counts['review']),
        ];

        if ($queue['cards'] === []) {
            return [
                'card'                => null,
                'counts'              => $counts,
                'progress'            => $progress,
                'next_due_in_seconds' => $this->secondsUntilNext($userId, $filters),
            ];
        }

        $row = $queue['cards'][0];

        return [
            'card'                => $this->presentCard($row, $userId),
            'counts'              => $counts,
            'progress'            => $progress,
            'next_due_in_seconds' => null,
        ];
    }

    /**
     * Monta o cartão para exibição, incluindo a prévia dos intervalos.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function presentCard(array $row, int $userId): array
    {
        $front = (string) $row['front'];
        $back  = (string) $row['back'];

        if ($row['card_type'] === FlashcardModel::TYPE_CLOZE && $row['cloze_index'] !== null) {
            $index = (int) $row['cloze_index'];
            $front = $this->validator->renderCloze((string) $row['front'], $index, false);
            $back  = $this->validator->renderCloze((string) $row['front'], $index, true);
        }

        return [
            'id'             => (int) $row['id'],
            'state_id'       => (int) $row['state_id'],
            'card_type'      => $row['card_type'],
            'front'          => $front,
            'back'           => $back,
            'explanation'    => $row['explanation'],
            'example'        => $row['example'],
            'source_excerpt' => $row['source_excerpt'],
            'state'          => (int) $row['state'],
            'state_label'    => FlashcardStateModel::STATE_LABELS[(int) $row['state']] ?? '',
            'reps'           => (int) $row['reps'],
            'lapses'         => (int) $row['lapses'],
            'version'        => (int) $row['version'],
            'state_version'  => (int) $row['state_version'],
            'intervals'      => $this->previewIntervals($row, $userId),
        ];
    }

    /**
     * Prévia dos quatro intervalos. A indisponibilidade do FSRS aqui não pode
     * derrubar a tela: os botões continuam funcionando, apenas sem o rótulo.
     *
     * @param array<string, mixed> $row
     *
     * @return array<int, string>
     */
    private function previewIntervals(array $row, int $userId): array
    {
        try {
            $preview = $this->fsrs->preview($row, $userId);
        } catch (FsrsUnavailableException $e) {
            log_message('warning', 'Prévia FSRS indisponível: {msg}', ['msg' => $e->getMessage()]);

            return [];
        }

        $labels = [];

        foreach ([1, 2, 3, 4] as $rating) {
            $labels[$rating] = (string) ($preview[$rating]['interval_label'] ?? '');
        }

        return $labels;
    }

    // ------------------------------------------------------------ Avaliação

    /**
     * Registra uma avaliação. Idempotente por `requestUuid`: a mesma requisição
     * enviada duas vezes (duplo clique, retry de rede) grava uma única revisão.
     *
     * @return array{review:array<string,mixed>, state:array<string,mixed>, duplicate:bool}
     */
    public function review(
        int $userId,
        string $sessionUuid,
        int $cardId,
        int $rating,
        string $requestUuid,
        ?int $expectedStateVersion = null,
        ?int $questionMs = null,
        ?int $answerMs = null
    ): array {
        if (! in_array($rating, [1, 2, 3, 4], true)) {
            throw new RuntimeException('Avaliação inválida.');
        }

        if (! $this->isUuid($requestUuid)) {
            throw new RuntimeException('Identificador da requisição inválido.');
        }

        $session   = $this->findSession($userId, $sessionUuid);
        $logModel  = new FlashcardReviewLogModel();

        // Idempotência: a requisição já foi processada.
        $existing = $logModel->where('uuid', $requestUuid)->where('user_id', $userId)->first();

        if ($existing !== null) {
            $state = (new FlashcardStateModel())->find((int) $existing['state_id']);

            return ['review' => $existing, 'state' => $state ?? [], 'duplicate' => true];
        }

        $stateModel = new FlashcardStateModel();
        $state      = $stateModel->where('flashcard_id', $cardId)->where('user_id', $userId)->first();

        if ($state === null) {
            throw new RuntimeException('Este cartão não está disponível para revisão.');
        }

        if ($expectedStateVersion !== null && (int) $state['version'] !== $expectedStateVersion) {
            throw new RuntimeException('Este cartão foi atualizado em outra aba. Recarregue a sessão.');
        }

        // Todo o agendamento vem do serviço FSRS. Se ele falhar, nada é gravado.
        $result = $this->fsrs->review($state, $rating, $userId);
        $next   = $this->fsrs->toDatabaseColumns($result['card']);

        $db = Database::connect();
        $db->transBegin();

        try {
            // Bloqueio otimista: só atualiza se a versão ainda for a lida.
            $updated = $db->table('study_flashcard_states')
                ->where('id', $state['id'])
                ->where('version', (int) $state['version'])
                ->update(array_merge($next, [
                    'version'    => (int) $state['version'] + 1,
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]));

            if (! $updated || $db->affectedRows() === 0) {
                throw new RuntimeException('Este cartão foi atualizado em outra aba. Recarregue a sessão.');
            }

            $reviewId = (int) $logModel->insert([
                'uuid'              => $requestUuid,
                'state_id'          => (int) $state['id'],
                'flashcard_id'      => $cardId,
                'user_id'           => $userId,
                'session_id'        => (int) $session['id'],
                'rating'            => $rating,
                'state_before'      => (int) $state['state'],
                'state_after'       => $next['state'],
                'due_before'        => $state['due'],
                'due_after'         => $next['due'],
                'stability_before'  => (float) $state['stability'],
                'stability_after'   => $next['stability'],
                'difficulty_before' => (float) $state['difficulty'],
                'difficulty_after'  => $next['difficulty'],
                'elapsed_days'      => $next['elapsed_days'],
                'scheduled_days'    => $next['scheduled_days'],
                'question_ms'       => $questionMs,
                'answer_ms'         => $answerMs,
                'reviewed_at'       => gmdate('Y-m-d H:i:s'),
                'fsrs_log'          => json_encode($result['log'], JSON_UNESCAPED_UNICODE),
            ], true);

            $db->table('study_flashcard_sessions')
                ->where('id', $session['id'])
                ->set('reviewed_total', 'reviewed_total + 1', false)
                ->set($this->ratingColumn($rating), $this->ratingColumn($rating) . ' + 1', false)
                ->update();

            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();

            throw $e instanceof RuntimeException ? $e : new RuntimeException('Não foi possível registrar a avaliação.');
        }

        $this->audit->log($userId, FlashcardAuditService::CARD_REVIEWED, 'card', $cardId, ['rating' => $rating]);

        return [
            'review'    => $logModel->find($reviewId),
            'state'     => $stateModel->find((int) $state['id']),
            'duplicate' => false,
        ];
    }

    /**
     * Desfaz a última avaliação da sessão. O log não é apagado — apenas marcado
     * como desfeito, preservando o histórico.
     *
     * @return array<string, mixed> cartão restaurado
     */
    public function undo(int $userId, string $sessionUuid): array
    {
        $session  = $this->findSession($userId, $sessionUuid);
        $logModel = new FlashcardReviewLogModel();

        $last = $logModel
            ->where('user_id', $userId)
            ->where('session_id', (int) $session['id'])
            ->where('undone', 0)
            ->orderBy('id', 'DESC')
            ->first();

        if ($last === null) {
            throw new RuntimeException('Não há resposta para desfazer nesta sessão.');
        }

        $stateModel = new FlashcardStateModel();
        $state      = $stateModel->find((int) $last['state_id']);

        if ($state === null) {
            throw new RuntimeException('Estado do cartão não encontrado.');
        }

        $fsrsLog  = json_decode((string) $last['fsrs_log'], true);
        $restored = is_array($fsrsLog) && $fsrsLog !== []
            ? $this->fsrs->toDatabaseColumns($this->fsrs->rollback($state, $fsrsLog, $userId))
            : $this->restoreFromLog($last);

        $db = Database::connect();
        $db->transBegin();

        try {
            $db->table('study_flashcard_states')
                ->where('id', (int) $state['id'])
                ->update(array_merge($restored, [
                    'version'    => (int) $state['version'] + 1,
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]));

            $db->table('study_flashcard_reviews')
                ->where('id', (int) $last['id'])
                ->update(['undone' => 1, 'updated_at' => gmdate('Y-m-d H:i:s')]);

            $column = $this->ratingColumn((int) $last['rating']);

            $db->table('study_flashcard_sessions')
                ->where('id', (int) $session['id'])
                ->set('reviewed_total', 'GREATEST(reviewed_total - 1, 0)', false)
                ->set($column, 'GREATEST(' . $column . ' - 1, 0)', false)
                ->update();

            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();

            throw new RuntimeException('Não foi possível desfazer a última resposta.');
        }

        $this->audit->log($userId, FlashcardAuditService::REVIEW_UNDONE, 'card', (int) $last['flashcard_id']);

        return $stateModel->find((int) $state['id']);
    }

    /**
     * Reconstrói o estado anterior a partir das colunas "_before" do log.
     * Usado apenas se o log do FSRS não estiver disponível.
     *
     * @param array<string, mixed> $log
     *
     * @return array<string, mixed>
     */
    private function restoreFromLog(array $log): array
    {
        return [
            'due'            => $log['due_before'] ?? gmdate('Y-m-d H:i:s'),
            'stability'      => (float) $log['stability_before'],
            'difficulty'     => (float) $log['difficulty_before'],
            'state'          => (int) $log['state_before'],
            'reps'           => 0,
            'lapses'         => 0,
            'elapsed_days'   => 0,
            'scheduled_days' => 0,
            'last_review'    => null,
        ];
    }

    // ---------------------------------------------------------- Encerramento

    /**
     * @return array<string, mixed> resumo da sessão
     */
    public function finish(int $userId, string $sessionUuid): array
    {
        $session = $this->findSession($userId, $sessionUuid);
        $model   = new FlashcardSessionModel();

        if ($session['status'] !== FlashcardSessionModel::STATUS_FINISHED) {
            $duration = max(0, time() - strtotime((string) $session['started_at'] . ' UTC'));

            $model->update((int) $session['id'], [
                'status'           => FlashcardSessionModel::STATUS_FINISHED,
                'finished_at'      => gmdate('Y-m-d H:i:s'),
                'duration_seconds' => $duration,
            ]);

            $session = $model->find((int) $session['id']);
        }

        return $this->summary($userId, $session);
    }

    /**
     * @param array<string, mixed> $session
     *
     * @return array<string, mixed>
     */
    public function summary(int $userId, array $session): array
    {
        $db = Database::connect();

        $times = $db->table('study_flashcard_reviews')
            ->select('COUNT(*) AS total, AVG(COALESCE(question_ms,0) + COALESCE(answer_ms,0)) AS avg_ms')
            ->where('session_id', (int) $session['id'])
            ->where('undone', 0)
            ->get()
            ->getRowArray() ?? ['total' => 0, 'avg_ms' => 0];

        $hardest = $db->table('study_flashcard_reviews r')
            ->select('s.name AS subject_name, COUNT(*) AS lapses')
            ->join('study_flashcards c', 'c.id = r.flashcard_id')
            ->join('study_subjects s', 's.id = c.subject_id', 'left')
            ->where('r.session_id', (int) $session['id'])
            ->where('r.undone', 0)
            ->where('r.rating', FlashcardReviewLogModel::RATING_AGAIN)
            ->groupBy('s.name')
            ->orderBy('lapses', 'DESC')
            ->get(1)
            ->getRowArray();

        $counts = $this->queue->counts($userId, [
            'subject_id' => $session['subject_id'],
            'topic_id'   => $session['topic_id'],
        ]);

        return [
            'session'      => $session,
            'reviewed'     => (int) $times['total'],
            'duration'     => (int) ($session['duration_seconds'] ?? 0),
            'average_ms'   => (int) round((float) ($times['avg_ms'] ?? 0)),
            'again'        => (int) $session['again_count'],
            'hard'         => (int) $session['hard_count'],
            'good'         => (int) $session['good_count'],
            'easy'         => (int) $session['easy_count'],
            'remaining'    => $counts,
            'hardest'      => $hardest['subject_name'] ?? null,
        ];
    }

    // ------------------------------------------------------------ Auxiliares

    /**
     * Segundos até o próximo cartão em aprendizado ficar disponível.
     */
    private function secondsUntilNext(int $userId, array $filters): ?int
    {
        $builder = Database::connect()
            ->table('study_flashcard_states s')
            ->select('s.due')
            ->join('study_flashcards c', 'c.id = s.flashcard_id')
            ->where('s.user_id', $userId)
            ->where('s.in_queue', 1)
            ->where('c.suspended', 0)
            ->where('c.deleted_at', null)
            ->whereIn('s.state', [FsrsClientService::STATE_LEARNING, FsrsClientService::STATE_RELEARNING])
            ->where('s.due >', gmdate('Y-m-d H:i:s'));

        if (! empty($filters['subject_id'])) {
            $builder->where('c.subject_id', (int) $filters['subject_id']);
        }

        $row = $builder->orderBy('s.due', 'ASC')->get(1)->getRowArray();

        if ($row === null) {
            return null;
        }

        return max(0, strtotime((string) $row['due'] . ' UTC') - time());
    }

    private function ratingColumn(int $rating): string
    {
        return [
            FlashcardReviewLogModel::RATING_AGAIN => 'again_count',
            FlashcardReviewLogModel::RATING_HARD  => 'hard_count',
            FlashcardReviewLogModel::RATING_GOOD  => 'good_count',
            FlashcardReviewLogModel::RATING_EASY  => 'easy_count',
        ][$rating];
    }

    private function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }

    public function uuid(): string
    {
        $bytes    = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
