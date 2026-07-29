<?php

declare(strict_types=1);

namespace App\Services\Flashcard;

use App\Models\FlashcardAiJobModel;
use App\Models\FlashcardAiSuggestionModel;
use App\Models\FlashcardModel;
use App\Models\FlashcardSourceModel;
use App\Models\StudySubjectModel;
use App\Models\StudyTopicModel;
use Config\Flashcards as FlashcardsConfig;
use RuntimeException;
use Throwable;

/**
 * Orquestra o ciclo de geração por IA: fonte → job → sugestões → aprovação.
 */
class FlashcardAiService
{
    private FlashcardsConfig $config;

    public function __construct(
        ?FlashcardsConfig $config = null,
        private ?ContentExtractorService $extractor = null,
        private ?OpenAiFlashcardService $openAi = null,
        private ?FlashcardValidationService $validator = null,
        private ?FlashcardDuplicateService $duplicates = null,
        private ?FlashcardService $flashcards = null,
        private ?AiUsageService $usage = null,
        private ?FlashcardAuditService $audit = null,
    ) {
        $this->config     = $config ?? config(FlashcardsConfig::class);
        $this->extractor  ??= new ContentExtractorService($this->config);
        $this->openAi     ??= new OpenAiFlashcardService($this->config);
        $this->validator  ??= new FlashcardValidationService();
        $this->duplicates ??= new FlashcardDuplicateService($this->validator);
        $this->flashcards ??= new FlashcardService($this->validator, $this->duplicates);
        $this->usage      ??= new AiUsageService($this->config);
        $this->audit      ??= new FlashcardAuditService();
    }

    // ------------------------------------------------------------ Criação

    /**
     * Cria a fonte e o job. O conteúdo é salvo antes de qualquer chamada à IA,
     * para que uma falha nunca faça o usuário perder o que enviou.
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed> job criado
     */
    public function createJob(int $userId, array $input): array
    {
        $this->usage->assertWithinLimits($userId);

        $type = (string) ($input['source_type'] ?? 'text');

        $extracted = $type === 'url'
            ? $this->extractor->fromUrl((string) ($input['url'] ?? ''))
            : $this->extractor->fromText((string) ($input['content'] ?? ''), (string) ($input['title'] ?? ''));

        $sourceModel = new FlashcardSourceModel();

        $sourceId = (int) $sourceModel->insert([
            'user_id'       => $userId,
            'subject_id'    => $this->intOrNull($input['subject_id'] ?? null),
            'topic_id'      => $this->intOrNull($input['topic_id'] ?? null),
            'source_type'   => $type,
            'title'         => (string) ($input['title'] ?? '') !== '' ? (string) $input['title'] : $extracted['title'],
            'url'           => $extracted['url'] !== '' ? $extracted['url'] : null,
            'raw_content'   => $type === 'url' ? null : (string) ($input['content'] ?? ''),
            'clean_content' => $extracted['text'],
            'content_hash'  => $extracted['hash'],
            'status'        => FlashcardSourceModel::STATUS_PENDING,
        ], true);

        $jobModel = new FlashcardAiJobModel();

        $jobId = (int) $jobModel->insert([
            'uuid'           => $this->uuid(),
            'user_id'        => $userId,
            'source_id'      => $sourceId,
            'job_type'       => 'generate',
            'model'          => $this->config->openAiModel,
            'status'         => FlashcardAiJobModel::STATUS_PENDING,
            'stage'          => 'queued',
            'prompt_version' => $this->config->promptVersion,
            'schema_version' => $this->config->schemaVersion,
            'options'        => json_encode([
                'quantity' => $this->intOrNull($input['quantity'] ?? null),
                'depth'    => (string) ($input['depth'] ?? 'balanced'),
                'types'    => $this->allowedTypes($input['types'] ?? null),
            ], JSON_UNESCAPED_UNICODE),
        ], true);

        return $jobModel->find($jobId);
    }

    // --------------------------------------------------------- Processamento

    /**
     * Executa o job: chama a IA, valida o resultado e grava as sugestões.
     *
     * @return array<string, mixed> job atualizado
     */
    public function processJob(int $jobId): array
    {
        $jobModel = new FlashcardAiJobModel();
        $job      = $jobModel->find($jobId);

        if ($job === null) {
            throw new RuntimeException('Job não encontrado.');
        }

        if (in_array($job['status'], [FlashcardAiJobModel::STATUS_DONE, FlashcardAiJobModel::STATUS_WARNING], true)) {
            return $job;
        }

        $sourceModel = new FlashcardSourceModel();
        $source      = $sourceModel->find((int) $job['source_id']);

        if ($source === null) {
            throw new RuntimeException('Fonte de estudo não encontrada.');
        }

        $started = microtime(true);

        $jobModel->update($jobId, [
            'status'     => FlashcardAiJobModel::STATUS_PROCESSING,
            'stage'      => 'generating',
            'attempts'   => (int) $job['attempts'] + 1,
            'started_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $sourceModel->update((int) $source['id'], ['status' => FlashcardSourceModel::STATUS_PROCESSING]);

        $options = json_decode((string) $job['options'], true) ?: [];
        $context = $this->contextNames($source);

        try {
            $chunks   = $this->extractor->chunk((string) $source['clean_content']);
            $cards    = [];
            $warnings = [];
            $summary  = '';
            $inTokens = 0;
            $outTokens = 0;
            $model     = (string) $job['model'];
            $responseId = null;

            foreach ($chunks as $index => $chunk) {
                $perChunk = $options['quantity'] ?? null;

                if ($perChunk !== null && count($chunks) > 1) {
                    $perChunk = max(2, (int) ceil((int) $perChunk / count($chunks)));
                }

                $result = $this->openAi->generate($chunk, array_merge($context, [
                    'quantity' => $perChunk,
                    'depth'    => $options['depth'] ?? 'balanced',
                    'types'    => $options['types'] ?? ['basic', 'cloze'],
                ]));

                $cards      = array_merge($cards, $result['cards']);
                $warnings   = array_merge($warnings, $result['warnings']);
                $summary    = $summary === '' ? $result['summary'] : $summary;
                $inTokens  += (int) $result['usage']['input_tokens'];
                $outTokens += (int) $result['usage']['output_tokens'];
                $model      = $result['model'];
                $responseId = $result['response_id'] ?? $responseId;

                if (count($cards) >= $this->config->maxCardsPerGeneration) {
                    if ($index < count($chunks) - 1) {
                        $warnings[] = 'O conteúdo é extenso: a geração parou ao atingir o limite de ' . $this->config->maxCardsPerGeneration . ' cartões.';
                    }
                    break;
                }
            }

            $jobModel->update($jobId, ['stage' => 'validating']);

            $stored = $this->storeSuggestions((int) $job['user_id'], $jobId, $source, $cards, $warnings);

            $duration = (int) round((microtime(true) - $started) * 1000);
            $cost     = $this->openAi->estimateCost($inTokens, $outTokens);

            $jobModel->update($jobId, [
                'status'         => $stored['warnings'] === [] ? FlashcardAiJobModel::STATUS_DONE : FlashcardAiJobModel::STATUS_WARNING,
                'stage'          => 'done',
                'model'          => $model,
                'input_tokens'   => $inTokens,
                'output_tokens'  => $outTokens,
                'estimated_cost' => $cost,
                'response_id'    => $responseId,
                'warnings'       => $stored['warnings'] === [] ? null : json_encode($stored['warnings'], JSON_UNESCAPED_UNICODE),
                'finished_at'    => gmdate('Y-m-d H:i:s'),
                'duration_ms'    => $duration,
            ]);

            $sourceModel->update((int) $source['id'], [
                'status'         => $stored['warnings'] === [] ? FlashcardSourceModel::STATUS_DONE : FlashcardSourceModel::STATUS_WARNING,
                'processed_at'   => gmdate('Y-m-d H:i:s'),
            ]);

            $this->audit->log((int) $job['user_id'], FlashcardAuditService::AI_GENERATED, 'job', $jobId, [
                'cards'  => $stored['count'],
                'model'  => $model,
                'tokens' => $inTokens + $outTokens,
            ]);
        } catch (Throwable $e) {
            $jobModel->update($jobId, [
                'status'        => FlashcardAiJobModel::STATUS_ERROR,
                'stage'         => 'error',
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
                'finished_at'   => gmdate('Y-m-d H:i:s'),
                'duration_ms'   => (int) round((microtime(true) - $started) * 1000),
            ]);

            // O conteúdo original permanece salvo para nova tentativa.
            $sourceModel->update((int) $source['id'], [
                'status'        => FlashcardSourceModel::STATUS_ERROR,
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
            ]);

            $this->audit->log((int) $job['user_id'], FlashcardAuditService::AI_FAILED, 'job', $jobId, [
                'error' => mb_substr($e->getMessage(), 0, 200),
            ]);

            throw $e;
        }

        return $jobModel->find($jobId);
    }

    /**
     * Valida cada cartão devolvido pela IA e grava as sugestões aprovadas
     * pela validação técnica (a aprovação humana vem depois).
     *
     * @param array<string, mixed> $source
     * @param list<array<string, mixed>> $cards
     * @param list<string> $warnings
     *
     * @return array{count:int, warnings:list<string>}
     */
    private function storeSuggestions(int $userId, int $jobId, array $source, array $cards, array $warnings): array
    {
        $model    = new FlashcardAiSuggestionModel();
        $stored   = 0;
        $rejected = 0;
        $dupes    = 0;
        $seen     = [];

        foreach ($cards as $raw) {
            if ($stored >= $this->config->maxCardsPerGeneration) {
                break;
            }

            if (! is_array($raw)) {
                $rejected++;
                continue;
            }

            $validation = $this->validator->validateCard([
                'card_type'      => $raw['type'] ?? 'basic',
                'question'       => $raw['question'] ?? '',
                'answer'         => $raw['answer'] ?? '',
                'explanation'    => $raw['explanation'] ?? null,
                'source_excerpt' => $raw['source_excerpt'] ?? null,
                'difficulty'     => $raw['difficulty'] ?? null,
                'tags'           => $raw['tags'] ?? [],
            ]);

            if (! $validation['valid']) {
                $rejected++;
                continue;
            }

            $card = $validation['card'];
            $key  = $this->duplicates->normalize($card['front']) . '::' . $this->duplicates->normalize($card['back']);

            if (isset($seen[$key])) {
                $dupes++;
                continue;
            }

            $seen[$key] = true;

            $signature = $this->duplicates->signature(
                $userId,
                $this->intOrNull($source['subject_id']),
                $this->intOrNull($source['topic_id']),
                $card['front'],
                $card['back']
            );

            $existing = $this->duplicates->findExisting($userId, $signature);

            $model->insert([
                'job_id'              => $jobId,
                'user_id'             => $userId,
                'subject_id'          => $this->intOrNull($source['subject_id']),
                'topic_id'            => $this->intOrNull($source['topic_id']),
                'card_type'           => $card['card_type'],
                'question'            => $card['front'],
                'answer'              => $card['back'],
                'explanation'         => $card['explanation'],
                'source_excerpt'      => $card['source_excerpt'],
                'tags'                => $card['tags'] === [] ? null : json_encode($card['tags'], JSON_UNESCAPED_UNICODE),
                'difficulty'          => $card['ai_difficulty'],
                'confidence'          => isset($raw['confidence']) ? max(0, min(1, (float) $raw['confidence'])) : null,
                'reverse_recommended' => (int) (bool) ($raw['reverse_recommended'] ?? false),
                'duplicate_of'        => $existing['id'] ?? null,
                'status'              => FlashcardAiSuggestionModel::STATUS_PENDING,
            ]);

            $stored++;
        }

        if ($stored === 0) {
            throw new RuntimeException('O conteúdo informado não possui informações suficientes para gerar flashcards úteis.');
        }

        if ($rejected > 0) {
            $warnings[] = $rejected . ' cartão(ões) foram descartados por não passarem na validação.';
        }

        if ($dupes > 0) {
            $warnings[] = $dupes . ' cartão(ões) repetidos foram removidos do resultado.';
        }

        return ['count' => $stored, 'warnings' => array_values(array_unique($warnings))];
    }

    // ------------------------------------------------------------ Aprovação

    /**
     * Aprova as sugestões escolhidas, gravando-as como cartões reais.
     *
     * @param list<int>                   $suggestionIds
     * @param array<int, array<string, mixed>> $edits alterações por id de sugestão
     *
     * @return array{created:int, duplicates:int, errors:list<string>}
     */
    public function approve(int $userId, string $jobUuid, array $suggestionIds, array $edits = []): array
    {
        $jobModel = new FlashcardAiJobModel();
        $job      = $jobModel->where('user_id', $userId)->where('uuid', $jobUuid)->first();

        if ($job === null) {
            throw new RuntimeException('Geração não encontrada.');
        }

        $model      = new FlashcardAiSuggestionModel();
        $created    = 0;
        $duplicates = 0;
        $errors     = [];

        foreach ($suggestionIds as $suggestionId) {
            $suggestion = $model->where('user_id', $userId)->where('job_id', (int) $job['id'])->find((int) $suggestionId);

            if ($suggestion === null || $suggestion['status'] !== FlashcardAiSuggestionModel::STATUS_PENDING) {
                continue;
            }

            $edit = $edits[(int) $suggestionId] ?? [];

            try {
                $result = $this->flashcards->createNote($userId, [
                    'card_type'      => $edit['card_type'] ?? $suggestion['card_type'],
                    'question'       => $edit['question'] ?? $suggestion['question'],
                    'answer'         => $edit['answer'] ?? $suggestion['answer'],
                    'explanation'    => $edit['explanation'] ?? $suggestion['explanation'],
                    'source_excerpt' => $suggestion['source_excerpt'],
                    'difficulty'     => $suggestion['difficulty'],
                    'tags'           => $suggestion['tags'],
                    'subject_id'     => $edit['subject_id'] ?? $suggestion['subject_id'],
                    'topic_id'       => $edit['topic_id'] ?? $suggestion['topic_id'],
                    'reverse'        => (bool) ($edit['reverse'] ?? false),
                ], [
                    'origin'       => 'ai',
                    'ai_generated' => true,
                    'source_id'    => $this->intOrNull($job['source_id']),
                ]);

                if ($result['errors'] !== []) {
                    $errors[] = implode(' ', array_column($result['errors'], 'message'));
                    continue;
                }

                $created    += count($result['cards']);
                $duplicates += count($result['duplicates']);

                $model->update((int) $suggestionId, [
                    'status'      => FlashcardAiSuggestionModel::STATUS_APPROVED,
                    'approved_at' => gmdate('Y-m-d H:i:s'),
                ]);
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($created > 0 && ! empty($job['source_id'])) {
            $sourceModel = new FlashcardSourceModel();
            $source      = $sourceModel->find((int) $job['source_id']);

            if ($source !== null) {
                $sourceModel->update((int) $source['id'], [
                    'cards_count' => (int) $source['cards_count'] + $created,
                ]);
            }
        }

        $this->audit->log($userId, FlashcardAuditService::AI_APPROVED, 'job', (int) $job['id'], ['created' => $created]);

        return ['created' => $created, 'duplicates' => $duplicates, 'errors' => $errors];
    }

    /**
     * Marca sugestões como rejeitadas.
     *
     * @param list<int> $suggestionIds
     */
    public function reject(int $userId, array $suggestionIds, string $reason = ''): int
    {
        if ($suggestionIds === []) {
            return 0;
        }

        $model = new FlashcardAiSuggestionModel();

        $model->where('user_id', $userId)
            ->whereIn('id', array_map('intval', $suggestionIds))
            ->where('status', FlashcardAiSuggestionModel::STATUS_PENDING)
            ->set([
                'status'           => FlashcardAiSuggestionModel::STATUS_REJECTED,
                'rejection_reason' => $reason !== '' ? $reason : null,
                'updated_at'       => gmdate('Y-m-d H:i:s'),
            ])
            ->update();

        return $model->db->affectedRows();
    }

    // ------------------------------------------- Melhoria de cartão existente

    /**
     * Pede à IA uma reescrita de um cartão problemático (§19). A sugestão só é
     * aplicada depois da aprovação do usuário.
     *
     * @return array<string, mixed> sugestões criadas
     */
    public function improveCard(int $userId, int $cardId): array
    {
        $this->usage->assertWithinLimits($userId);

        $card = $this->flashcards->findOwned($userId, $cardId);

        $jobModel = new FlashcardAiJobModel();
        $jobId    = (int) $jobModel->insert([
            'uuid'           => $this->uuid(),
            'user_id'        => $userId,
            'flashcard_id'   => $cardId,
            'job_type'       => 'improve',
            'model'          => $this->config->openAiModel,
            'status'         => FlashcardAiJobModel::STATUS_PROCESSING,
            'prompt_version' => $this->config->promptVersion,
            'schema_version' => $this->config->schemaVersion,
            'started_at'     => gmdate('Y-m-d H:i:s'),
        ], true);

        $context = "Cartão atual que precisa ser melhorado.\n\n"
            . 'Pergunta: ' . $this->validator->plainText($card['front']) . "\n"
            . 'Resposta: ' . $this->validator->plainText($card['back']) . "\n"
            . 'Explicação: ' . $this->validator->plainText($card['explanation'] ?? '') . "\n"
            . 'Trecho da fonte: ' . $this->validator->plainText($card['source_excerpt'] ?? '') . "\n\n"
            . 'Reescreva-o de forma mais objetiva. Se a pergunta cobrar mais de uma informação, '
            . 'divida em cartões menores. Mantenha-se fiel ao trecho da fonte.';

        try {
            $result = $this->openAi->generate($context, ['depth' => 'essential', 'types' => ['basic', 'cloze'], 'quantity' => 3]);

            $source = [
                'subject_id' => $card['subject_id'],
                'topic_id'   => $card['topic_id'],
            ];

            $stored = $this->storeSuggestions($userId, $jobId, $source, $result['cards'], $result['warnings']);

            $jobModel->update($jobId, [
                'status'         => FlashcardAiJobModel::STATUS_DONE,
                'input_tokens'   => $result['usage']['input_tokens'],
                'output_tokens'  => $result['usage']['output_tokens'],
                'estimated_cost' => $this->openAi->estimateCost($result['usage']['input_tokens'], $result['usage']['output_tokens']),
                'finished_at'    => gmdate('Y-m-d H:i:s'),
            ]);

            return ['job' => $jobModel->find($jobId), 'count' => $stored['count']];
        } catch (Throwable $e) {
            $jobModel->update($jobId, [
                'status'        => FlashcardAiJobModel::STATUS_ERROR,
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
                'finished_at'   => gmdate('Y-m-d H:i:s'),
            ]);

            throw $e;
        }
    }

    // ------------------------------------------------------------ Auxiliares

    /**
     * @param array<string, mixed> $source
     *
     * @return array{subject?:string, topic?:string}
     */
    private function contextNames(array $source): array
    {
        $context = [];

        if (! empty($source['subject_id'])) {
            $subject = (new StudySubjectModel())->find((int) $source['subject_id']);

            if ($subject !== null) {
                $context['subject'] = $subject['name'];
            }
        }

        if (! empty($source['topic_id'])) {
            $topic = (new StudyTopicModel())->find((int) $source['topic_id']);

            if ($topic !== null) {
                $context['topic'] = $topic['name'];
            }
        }

        return $context;
    }

    /**
     * @return list<string>
     */
    private function allowedTypes($types): array
    {
        $valid = [FlashcardModel::TYPE_BASIC, FlashcardModel::TYPE_CLOZE, FlashcardModel::TYPE_TYPED_ANSWER];

        if (! is_array($types)) {
            return [FlashcardModel::TYPE_BASIC, FlashcardModel::TYPE_CLOZE];
        }

        $filtered = array_values(array_intersect($types, $valid));

        return $filtered === [] ? [FlashcardModel::TYPE_BASIC] : $filtered;
    }

    private function intOrNull($value): ?int
    {
        return $value === null || $value === '' || (int) $value === 0 ? null : (int) $value;
    }

    private function uuid(): string
    {
        $bytes    = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
