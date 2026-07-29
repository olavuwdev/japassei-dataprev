<?php

declare(strict_types=1);

namespace App\Services\Flashcard;

use App\Models\FlashcardImportModel;
use App\Models\FlashcardModel;
use App\Models\FlashcardSourceModel;
use Config\Database;
use Config\Flashcards as FlashcardsConfig;
use RuntimeException;
use Throwable;

/**
 * Importação de flashcards gerados fora do sistema (§33).
 *
 * Aceita o JSON completo e o simplificado, resolve a taxonomia, previne
 * duplicidades em três níveis e inicializa cada cartão como Novo no FSRS.
 */
class FlashcardApiImportService
{
    private FlashcardsConfig $config;

    public function __construct(
        ?FlashcardsConfig $config = null,
        private ?FlashcardTaxonomyResolverService $taxonomy = null,
        private ?FlashcardValidationService $validator = null,
        private ?FlashcardDuplicateService $duplicates = null,
        private ?FlashcardService $flashcards = null,
        private ?FlashcardAuditService $audit = null,
    ) {
        $this->config     = $config ?? config(FlashcardsConfig::class);
        $this->taxonomy   ??= new FlashcardTaxonomyResolverService();
        $this->validator  ??= new FlashcardValidationService();
        $this->duplicates ??= new FlashcardDuplicateService($this->validator);
        $this->flashcards ??= new FlashcardService($this->validator, $this->duplicates);
        $this->audit      ??= new FlashcardAuditService();
    }

    /**
     * Processa uma importação completa.
     *
     * @param array<string, mixed> $payload
     * @param array{token_id?:?int, requires_approval?:bool, idempotency_key?:?string, ip?:?string, user_agent?:?string} $meta
     *
     * @return array{status:int, body:array<string, mixed>}
     */
    public function import(int $userId, array $payload, array $meta = []): array
    {
        $normalized = $this->normalizePayload($payload);
        $cards      = $normalized['cards'];

        if ($cards === []) {
            return $this->error(422, 'Envie ao menos um cartão em "cards".');
        }

        if (count($cards) > $this->config->apiMaxCardsPerRequest) {
            return $this->error(
                422,
                'Máximo de ' . $this->config->apiMaxCardsPerRequest . ' cartões por requisição. Recebidos: ' . count($cards) . '.'
            );
        }

        $importModel = new FlashcardImportModel();

        // Idempotência do lote: mesma chave ou mesmo external_id não reprocessa.
        $existing = $this->findPreviousImport($userId, $meta['idempotency_key'] ?? null, $normalized['external_id']);

        if ($existing !== null) {
            $body = json_decode((string) $existing['response_payload'], true);

            return [
                'status' => 409,
                'body'   => is_array($body)
                    ? array_merge($body, ['message' => 'Esta importação já foi processada anteriormente.', 'duplicate_request' => true])
                    : ['success' => false, 'message' => 'Esta importação já foi processada anteriormente.'],
            ];
        }

        if ($this->countToday($userId) >= $this->config->apiDailyImportLimit) {
            return $this->error(429, 'Limite diário de importações atingido.');
        }

        $importId = (int) $importModel->insert([
            'uuid'            => $this->uuid(),
            'external_id'     => $normalized['external_id'],
            'user_id'         => $userId,
            'token_id'        => $meta['token_id'] ?? null,
            'provider'        => $normalized['provider'],
            'idempotency_key' => $meta['idempotency_key'] ?? null,
            'status'          => FlashcardImportModel::STATUS_PROCESSING,
            'received_count'  => count($cards),
            'ip_address'      => $meta['ip'] ?? null,
            'user_agent'      => mb_substr((string) ($meta['user_agent'] ?? ''), 0, 500) ?: null,
        ], true);

        try {
            $resolved = $this->taxonomy->resolve($normalized);
        } catch (TaxonomyNotFoundException $e) {
            $this->failImport($importId, $e->getMessage());

            return $this->error(404, $e->getMessage());
        } catch (Throwable $e) {
            $this->failImport($importId, $e->getMessage());

            return $this->error(422, $e->getMessage());
        }

        $settings = $normalized['settings'];

        // A configuração do token tem precedência sobre o pedido do cliente:
        // um token que exige aprovação nunca cadastra direto.
        $requiresApproval = ($meta['requires_approval'] ?? true) || $settings['requires_approval'];

        $sourceId = $this->createSource($userId, $normalized, $resolved, $importId);

        $result = $this->processCards($userId, $cards, $resolved, $settings, [
            'requires_approval' => $requiresApproval,
            'source_id'         => $sourceId,
            'import_id'         => $importId,
            'atomic'            => $normalized['atomic'],
        ]);

        if ($normalized['atomic'] && $result['rejected'] > 0) {
            $this->failImport($importId, 'Importação atômica cancelada por erro de validação.');

            return [
                'status' => 422,
                'body'   => [
                    'success' => false,
                    'message' => 'A importação foi cancelada: no modo atômico qualquer erro impede o cadastro do lote.',
                    'summary' => [
                        'received'  => count($cards),
                        'created'   => 0,
                        'duplicates' => $result['duplicates'],
                        'rejected'  => $result['rejected'],
                    ],
                    'errors' => $result['errors'],
                ],
            ];
        }

        $status = $result['rejected'] > 0 ? FlashcardImportModel::STATUS_PARTIAL : FlashcardImportModel::STATUS_DONE;

        $body = [
            'success'   => true,
            'message'   => $result['rejected'] > 0 ? 'A importação foi concluída com alertas.' : 'Importação concluída.',
            'import_id' => $importModel->find($importId)['uuid'],
            'discipline' => $this->taxonomyResponse($resolved['discipline']),
            'category'   => $this->taxonomyResponse($resolved['category']),
            'subject'    => $this->taxonomyResponse($resolved['subject']),
            'summary'   => [
                'received'         => count($cards),
                'created'          => $result['created'],
                'duplicates'       => $result['duplicates'],
                'rejected'         => $result['rejected'],
                'pending_approval' => $result['pending'],
            ],
            'cards' => $result['cards'],
        ];

        if ($result['rejected'] > 0) {
            $body['partial_success'] = true;
            $body['errors']          = $result['errors'];
        }

        $importModel->update($importId, [
            'status'           => $status,
            'created_count'    => $result['created'],
            'duplicate_count'  => $result['duplicates'],
            'rejected_count'   => $result['rejected'],
            'pending_count'    => $result['pending'],
            'response_payload' => json_encode($body, JSON_UNESCAPED_UNICODE),
            'processed_at'     => gmdate('Y-m-d H:i:s'),
        ]);

        if ($sourceId !== null) {
            (new FlashcardSourceModel())->update($sourceId, [
                'status'       => FlashcardSourceModel::STATUS_DONE,
                'cards_count'  => $result['created'],
                'processed_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }

        $this->audit->log($userId, FlashcardAuditService::IMPORT_RECEIVED, 'import', $importId, [
            'created'  => $result['created'],
            'provider' => $normalized['provider'],
        ]);

        return [
            'status' => $result['rejected'] > 0 ? 207 : ($result['created'] > 0 ? 201 : 200),
            'body'   => $body,
        ];
    }

    // ------------------------------------------------------------ Cartões

    /**
     * @param list<array<string, mixed>> $cards
     * @param array<string, mixed>       $resolved
     * @param array<string, mixed>       $settings
     * @param array<string, mixed>       $context
     *
     * @return array{created:int, duplicates:int, rejected:int, pending:int, cards:list<array<string,mixed>>, errors:list<array<string,string>>}
     */
    private function processCards(int $userId, array $cards, array $resolved, array $settings, array $context): array
    {
        $subjectId = $resolved['discipline']['id'];
        $topicId   = $resolved['subject']['id'] ?? $resolved['category']['id'];

        $created = 0;
        $dupes   = 0;
        $rejects = 0;
        $pending = 0;
        $results = [];
        $errors  = [];
        $seen    = [];

        $db = Database::connect();
        $db->transBegin();

        try {
            foreach ($cards as $index => $raw) {
                $externalId = isset($raw['external_id']) ? (string) $raw['external_id'] : null;
                $validation = $this->validator->validateCard($raw);

                if (! $validation['valid']) {
                    $rejects++;

                    foreach ($validation['errors'] as $error) {
                        $errors[] = array_merge(['external_id' => $externalId ?? ('#' . ($index + 1))], $error);
                    }

                    $results[] = ['external_id' => $externalId, 'id' => null, 'status' => 'rejected'];
                    continue;
                }

                $card = $validation['card'];

                // Nível 2: identificador do cartão já importado antes.
                if ($externalId !== null && $this->externalIdExists($userId, $externalId)) {
                    $dupes++;
                    $results[] = ['external_id' => $externalId, 'id' => null, 'status' => 'duplicate'];
                    continue;
                }

                // Nível 3 (dentro do lote): mesma pergunta e resposta.
                $key = $this->duplicates->normalize($card['front']) . '::' . $this->duplicates->normalize($card['back']);

                if ($settings['prevent_duplicates'] && isset($seen[$key])) {
                    $dupes++;
                    $results[] = ['external_id' => $externalId, 'id' => null, 'status' => 'duplicate'];
                    continue;
                }

                $seen[$key] = true;

                $outcome = $this->flashcards->createNote($userId, array_merge($card, [
                    'question'    => $card['front'],
                    'answer'      => $card['back'],
                    'subject_id'  => $subjectId,
                    'topic_id'    => $topicId,
                    'external_id' => $externalId,
                ]), [
                    'origin'          => 'external_api',
                    'ai_generated'    => true,
                    'source_id'       => $context['source_id'],
                    'import_id'       => $context['import_id'],
                    'status'          => $context['requires_approval'] ? FlashcardModel::STATUS_PENDING_APPROVAL : FlashcardModel::STATUS_ACTIVE,
                    'in_queue'        => $context['requires_approval'] ? false : $settings['send_to_review'],
                    'skip_duplicates' => $settings['prevent_duplicates'],
                ]);

                if ($outcome['cards'] === []) {
                    $dupes++;
                    $results[] = ['external_id' => $externalId, 'id' => null, 'status' => 'duplicate'];
                    continue;
                }

                foreach ($outcome['cards'] as $saved) {
                    $created++;

                    if ($context['requires_approval']) {
                        $pending++;
                    }

                    $results[] = [
                        'external_id' => $externalId,
                        'id'          => (int) $saved['id'],
                        'status'      => $context['requires_approval'] ? 'pending_approval' : 'created',
                    ];
                }

                $dupes += count($outcome['duplicates']);
            }

            if ($context['atomic'] && $rejects > 0) {
                $db->transRollback();

                return ['created' => 0, 'duplicates' => $dupes, 'rejected' => $rejects, 'pending' => 0, 'cards' => [], 'errors' => $errors];
            }

            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();

            throw new RuntimeException('Falha ao gravar os cartões: ' . $e->getMessage());
        }

        return [
            'created'    => $created,
            'duplicates' => $dupes,
            'rejected'   => $rejects,
            'pending'    => $pending,
            'cards'      => $results,
            'errors'     => $errors,
        ];
    }

    // -------------------------------------------------------- Normalização

    /**
     * Converte o JSON simplificado no formato interno completo e aplica
     * os padrões documentados.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function normalizePayload(array $payload): array
    {
        $createMissing = (bool) ($payload['create_missing_categories'] ?? false);

        $settings = (array) ($payload['settings'] ?? []);

        return [
            'external_id' => isset($payload['external_id']) ? mb_substr((string) $payload['external_id'], 0, 191) : null,
            'provider'    => mb_substr((string) ($payload['source']['provider'] ?? $payload['provider'] ?? 'external_ai'), 0, 50),
            'atomic'      => (bool) ($payload['atomic'] ?? false),
            'source'      => [
                'type'            => (string) ($payload['source']['type'] ?? 'external_ai'),
                'title'           => (string) ($payload['source']['title'] ?? ''),
                'url'             => $payload['source']['url'] ?? null,
                'content_summary' => (string) ($payload['source']['content_summary'] ?? ''),
            ],
            'discipline' => $this->taxonomyInput($payload['discipline'] ?? null, $createMissing),
            'category'   => $this->taxonomyInput($payload['category'] ?? null, $createMissing),
            'subject'    => $this->taxonomyInput($payload['subject'] ?? null, $createMissing),
            'settings'   => [
                'send_to_review'     => (bool) ($settings['send_to_review'] ?? true),
                'requires_approval'  => (bool) ($settings['requires_approval'] ?? false),
                'prevent_duplicates' => (bool) ($settings['prevent_duplicates'] ?? true),
            ],
            'cards' => array_values(array_filter((array) ($payload['cards'] ?? []), 'is_array')),
        ];
    }

    /**
     * Aceita `"Direito Administrativo"` ou `{"name": "...", "create_if_not_exists": true}`.
     *
     * @return array{name:string, create_if_not_exists:bool}|null
     */
    private function taxonomyInput($input, bool $createMissing): ?array
    {
        if (is_string($input)) {
            $name = trim($input);

            return $name === '' ? null : ['name' => $name, 'create_if_not_exists' => $createMissing];
        }

        if (! is_array($input)) {
            return null;
        }

        $name = trim((string) ($input['name'] ?? ''));

        if ($name === '') {
            return null;
        }

        return [
            'name'                 => $name,
            'create_if_not_exists' => (bool) ($input['create_if_not_exists'] ?? $createMissing),
        ];
    }

    // ------------------------------------------------------------ Auxiliares

    /**
     * @param array<string, mixed> $normalized
     * @param array<string, mixed> $resolved
     */
    private function createSource(int $userId, array $normalized, array $resolved, int $importId): ?int
    {
        $title = $normalized['source']['title'] !== ''
            ? $normalized['source']['title']
            : 'Importação via ' . $normalized['provider'];

        $summary = $normalized['source']['content_summary'];

        return (int) (new FlashcardSourceModel())->insert([
            'user_id'       => $userId,
            'subject_id'    => $resolved['discipline']['id'],
            'topic_id'      => $resolved['subject']['id'] ?? $resolved['category']['id'],
            'source_type'   => 'external_ai',
            'provider'      => $normalized['provider'],
            'title'         => mb_substr($title, 0, 255),
            'url'           => $normalized['source']['url'],
            'clean_content' => $summary !== '' ? $summary : null,
            'content_hash'  => $summary !== '' ? hash('sha256', $summary) : null,
            'status'        => FlashcardSourceModel::STATUS_PROCESSING,
        ], true) ?: null;
    }

    /**
     * @param array{id:?int, name:?string, created:bool} $item
     *
     * @return array<string, mixed>|null
     */
    private function taxonomyResponse(array $item): ?array
    {
        if ($item['id'] === null) {
            return null;
        }

        return ['id' => $item['id'], 'name' => $item['name'], 'created' => $item['created']];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findPreviousImport(int $userId, ?string $idempotencyKey, ?string $externalId): ?array
    {
        if ($idempotencyKey === null && $externalId === null) {
            return null;
        }

        $model = (new FlashcardImportModel())->where('user_id', $userId);

        $model->groupStart();

        if ($idempotencyKey !== null) {
            $model->where('idempotency_key', $idempotencyKey);
        }

        if ($externalId !== null) {
            $idempotencyKey === null
                ? $model->where('external_id', $externalId)
                : $model->orWhere('external_id', $externalId);
        }

        $model->groupEnd();

        return $model->whereIn('status', [
            FlashcardImportModel::STATUS_DONE,
            FlashcardImportModel::STATUS_PARTIAL,
            FlashcardImportModel::STATUS_PROCESSING,
        ])->orderBy('id', 'DESC')->first();
    }

    private function externalIdExists(int $userId, string $externalId): bool
    {
        return (new FlashcardModel())
            ->where('user_id', $userId)
            ->where('external_id', $externalId)
            ->countAllResults() > 0;
    }

    private function countToday(int $userId): int
    {
        return (new FlashcardImportModel())
            ->where('user_id', $userId)
            ->where('created_at >=', gmdate('Y-m-d 00:00:00'))
            ->countAllResults();
    }

    private function failImport(int $importId, string $message): void
    {
        (new FlashcardImportModel())->update($importId, [
            'status'        => FlashcardImportModel::STATUS_ERROR,
            'error_message' => mb_substr($message, 0, 2000),
            'processed_at'  => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array{status:int, body:array<string, mixed>}
     */
    private function error(int $status, string $message): array
    {
        return ['status' => $status, 'body' => ['success' => false, 'message' => $message]];
    }

    private function uuid(): string
    {
        $bytes    = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
