<?php

declare(strict_types=1);

namespace App\Services\Flashcard;

use App\Models\FlashcardModel;
use App\Models\FlashcardNoteModel;
use App\Models\FlashcardStateModel;
use App\Models\StudySubjectModel;
use App\Models\StudyTopicModel;
use RuntimeException;

/**
 * Criação, edição e consulta dos cartões. Uma anotação concentra a informação
 * central e gera um ou mais cartões (básico, reverso, cloze numerado).
 */
class FlashcardService
{
    public function __construct(
        private ?FlashcardValidationService $validator = null,
        private ?FlashcardDuplicateService $duplicates = null,
        private ?FsrsClientService $fsrs = null,
        private ?FlashcardAuditService $audit = null,
    ) {
        $this->validator  ??= new FlashcardValidationService();
        $this->duplicates ??= new FlashcardDuplicateService($this->validator);
        $this->fsrs       ??= new FsrsClientService();
        $this->audit      ??= new FlashcardAuditService();
    }

    // ------------------------------------------------------------- Criação

    /**
     * Cria uma anotação e seus cartões.
     *
     * @param array<string, mixed> $input
     * @param array{origin?:string, ai_generated?:bool, source_id?:?int, import_id?:?int, status?:string, in_queue?:bool, skip_duplicates?:bool} $options
     *
     * @return array{note_id:int, cards:list<array<string,mixed>>, duplicates:list<array<string,mixed>>, errors:list<array<string,string>>}
     */
    public function createNote(int $userId, array $input, array $options = []): array
    {
        $validation = $this->validator->validateCard($input);

        if (! $validation['valid']) {
            return ['note_id' => 0, 'cards' => [], 'duplicates' => [], 'errors' => $validation['errors']];
        }

        $card       = $validation['card'];
        $subjectId  = $this->intOrNull($input['subject_id'] ?? null);
        $topicId    = $this->intOrNull($input['topic_id'] ?? null);
        $reverse    = (bool) ($input['reverse'] ?? false);
        $status     = (string) ($options['status'] ?? FlashcardModel::STATUS_ACTIVE);
        $inQueue    = (bool) ($options['in_queue'] ?? true);
        $skipDupes  = (bool) ($options['skip_duplicates'] ?? true);

        $noteModel = new FlashcardNoteModel();
        $noteId    = (int) $noteModel->insert([
            'source_id'    => $options['source_id'] ?? null,
            'user_id'      => $userId,
            'subject_id'   => $subjectId,
            'topic_id'     => $topicId,
            'note_type'    => $card['card_type'],
            'base_content' => $input['base_content'] ?? null,
            'tags'         => $card['tags'] === [] ? null : json_encode($card['tags'], JSON_UNESCAPED_UNICODE),
            'ai_generated' => (int) ($options['ai_generated'] ?? false),
            'origin'       => (string) ($options['origin'] ?? 'manual'),
            'status'       => 'active',
        ], true);

        if ($noteId === 0) {
            throw new RuntimeException('Não foi possível salvar a anotação.');
        }

        $variants = $this->expandVariants($card, $reverse);
        $created  = [];
        $dupes    = [];

        foreach ($variants as $order => $variant) {
            $signature = $this->duplicates->signature(
                $userId,
                $subjectId,
                $topicId,
                $variant['front'],
                $variant['back'],
                $variant['cloze_index'] ?? null
            );

            if ($skipDupes) {
                $existing = $this->duplicates->findExisting($userId, $signature);

                if ($existing !== null) {
                    $dupes[] = $existing;
                    continue;
                }
            }

            $created[] = $this->insertCard($userId, $noteId, $subjectId, $topicId, $variant, [
                'sort_order'        => $order,
                'content_signature' => $signature,
                'status'            => $status,
                'external_id'       => $card['external_id'],
                'import_id'         => $options['import_id'] ?? null,
                'in_queue'          => $inQueue,
            ]);
        }

        if ($created === []) {
            // Nenhum cartão sobreviveu (todos duplicados): a anotação não serve.
            $noteModel->delete($noteId, true);
        } else {
            $this->audit->log($userId, FlashcardAuditService::CARD_CREATED, 'note', $noteId, [
                'cards'  => count($created),
                'origin' => $options['origin'] ?? 'manual',
            ]);
        }

        return ['note_id' => $noteId, 'cards' => $created, 'duplicates' => $dupes, 'errors' => []];
    }

    /**
     * Expande a anotação validada nos cartões que ela realmente gera.
     *
     * @param array<string, mixed> $card
     *
     * @return list<array<string, mixed>>
     */
    private function expandVariants(array $card, bool $reverse): array
    {
        if ($card['card_type'] === FlashcardModel::TYPE_CLOZE) {
            $variants = [];

            foreach ($this->validator->parseCloze($card['front']) as $gap) {
                $variants[] = $card + ['cloze_index' => $gap['index']];
            }

            return $variants;
        }

        $variants = [$card + ['cloze_index' => null]];

        if ($reverse && $card['card_type'] === FlashcardModel::TYPE_BASIC) {
            $variants[] = array_merge($card, [
                'card_type'   => FlashcardModel::TYPE_REVERSE,
                'front'       => $card['back'],
                'back'        => $card['front'],
                'cloze_index' => null,
            ]);
        }

        return $variants;
    }

    /**
     * @param array<string, mixed> $variant
     * @param array<string, mixed> $meta
     *
     * @return array<string, mixed>
     */
    private function insertCard(int $userId, int $noteId, ?int $subjectId, ?int $topicId, array $variant, array $meta): array
    {
        $model = new FlashcardModel();

        $id = (int) $model->insert([
            'note_id'           => $noteId,
            'user_id'           => $userId,
            'subject_id'        => $subjectId,
            'topic_id'          => $topicId,
            'card_type'         => $variant['card_type'],
            'front'             => $variant['front'],
            'back'              => $variant['back'],
            'explanation'       => $variant['explanation'] ?? null,
            'example'           => $variant['example'] ?? null,
            'source_excerpt'    => $variant['source_excerpt'] ?? null,
            'cloze_index'       => $variant['cloze_index'] ?? null,
            'sort_order'        => $meta['sort_order'],
            'ai_difficulty'     => $variant['ai_difficulty'] ?? null,
            'ai_confidence'     => $variant['ai_confidence'] ?? null,
            'content_signature' => $meta['content_signature'],
            'external_id'       => $meta['external_id'],
            'import_id'         => $meta['import_id'],
            'status'            => $meta['status'],
        ], true);

        if ($id === 0) {
            throw new RuntimeException('Não foi possível salvar o cartão.');
        }

        $this->initializeState($userId, $id, (bool) $meta['in_queue']);

        return $model->find($id);
    }

    /**
     * Cria o estado FSRS inicial do cartão (estado Novo, sem data fixa).
     */
    public function initializeState(int $userId, int $flashcardId, bool $inQueue = true): void
    {
        $model = new FlashcardStateModel();

        $existing = $model->where('flashcard_id', $flashcardId)->where('user_id', $userId)->first();

        if ($existing !== null) {
            return;
        }

        $empty = $this->fsrs->emptyCard();

        $model->insert([
            'flashcard_id'   => $flashcardId,
            'user_id'        => $userId,
            'due'            => $empty['due'],
            'stability'      => $empty['stability'],
            'difficulty'     => $empty['difficulty'],
            'elapsed_days'   => 0,
            'scheduled_days' => 0,
            'reps'           => 0,
            'lapses'         => 0,
            'state'          => FsrsClientService::STATE_NEW,
            'learning_step'  => 0,
            'last_review'    => null,
            'in_queue'       => $inQueue ? 1 : 0,
            'version'        => 1,
        ]);
    }

    // ------------------------------------------------------------- Edição

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function updateCard(int $userId, int $cardId, array $input): array
    {
        $model = new FlashcardModel();
        $card  = $this->findOwned($userId, $cardId);

        $validation = $this->validator->validateCard(array_merge([
            'card_type' => $card['card_type'],
            'front'     => $card['front'],
            'back'      => $card['back'],
        ], $input));

        if (! $validation['valid']) {
            throw new RuntimeException(implode(' ', array_column($validation['errors'], 'message')));
        }

        $clean     = $validation['card'];
        $subjectId = array_key_exists('subject_id', $input) ? $this->intOrNull($input['subject_id']) : $this->intOrNull($card['subject_id']);
        $topicId   = array_key_exists('topic_id', $input) ? $this->intOrNull($input['topic_id']) : $this->intOrNull($card['topic_id']);

        $model->update($cardId, [
            'subject_id'        => $subjectId,
            'topic_id'          => $topicId,
            'card_type'         => $clean['card_type'],
            'front'             => $clean['front'],
            'back'              => $clean['back'],
            'explanation'       => $clean['explanation'],
            'example'           => $clean['example'],
            'source_excerpt'    => $clean['source_excerpt'],
            'content_signature' => $this->duplicates->signature(
                $userId,
                $subjectId,
                $topicId,
                $clean['front'],
                $clean['back'],
                $card['cloze_index'] === null ? null : (int) $card['cloze_index']
            ),
            'edit_count'        => (int) $card['edit_count'] + 1,
            'version'           => (int) $card['version'] + 1,
        ]);

        $this->audit->log($userId, FlashcardAuditService::CARD_UPDATED, 'card', $cardId);

        return $model->find($cardId);
    }

    public function deleteCard(int $userId, int $cardId): void
    {
        $this->findOwned($userId, $cardId);

        (new FlashcardModel())->delete($cardId);
        $this->audit->log($userId, FlashcardAuditService::CARD_DELETED, 'card', $cardId);
    }

    /**
     * @return array<string, mixed>
     */
    public function toggleSuspend(int $userId, int $cardId, ?bool $suspended = null): array
    {
        $model = new FlashcardModel();
        $card  = $this->findOwned($userId, $cardId);

        $value = $suspended ?? ! (bool) $card['suspended'];
        $model->update($cardId, ['suspended' => $value ? 1 : 0]);

        $this->audit->log(
            $userId,
            $value ? FlashcardAuditService::CARD_SUSPENDED : FlashcardAuditService::CARD_RESUMED,
            'card',
            $cardId
        );

        return $model->find($cardId);
    }

    /**
     * Ativa cartões que foram importados fora da fila (`send_to_review: false`).
     */
    public function setQueued(int $userId, int $cardId, bool $inQueue): void
    {
        $this->findOwned($userId, $cardId);

        (new FlashcardStateModel())
            ->where('flashcard_id', $cardId)
            ->where('user_id', $userId)
            ->set(['in_queue' => $inQueue ? 1 : 0])
            ->update();
    }

    // ------------------------------------------------------------- Consultas

    /**
     * @return array<string, mixed>
     */
    public function findOwned(int $userId, int $cardId): array
    {
        $card = (new FlashcardModel())->where('user_id', $userId)->find($cardId);

        if ($card === null) {
            throw new RuntimeException('Cartão não encontrado.');
        }

        return $card;
    }

    /**
     * Listagem paginada com filtros. Nunca carrega o verso de todos os cartões
     * de uma vez em listagens grandes — a paginação limita o volume.
     *
     * @param array<string, mixed> $filters
     *
     * @return array{cards:list<array<string,mixed>>, total:int, page:int, pages:int, per_page:int}
     */
    public function listCards(int $userId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $builder = (new FlashcardModel())
            ->select('study_flashcards.*, study_subjects.name AS subject_name, study_topics.name AS topic_name,
                      study_flashcard_states.state, study_flashcard_states.due, study_flashcard_states.reps,
                      study_flashcard_states.lapses, study_flashcard_states.in_queue')
            ->join('study_subjects', 'study_subjects.id = study_flashcards.subject_id', 'left')
            ->join('study_topics', 'study_topics.id = study_flashcards.topic_id', 'left')
            ->join('study_flashcard_states', 'study_flashcard_states.flashcard_id = study_flashcards.id AND study_flashcard_states.user_id = study_flashcards.user_id', 'left')
            ->where('study_flashcards.user_id', $userId);

        if (! empty($filters['subject_id'])) {
            $builder->where('study_flashcards.subject_id', (int) $filters['subject_id']);
        }

        if (! empty($filters['topic_id'])) {
            $builder->where('study_flashcards.topic_id', (int) $filters['topic_id']);
        }

        if (! empty($filters['card_type'])) {
            $builder->where('study_flashcards.card_type', (string) $filters['card_type']);
        }

        if (! empty($filters['status'])) {
            $builder->where('study_flashcards.status', (string) $filters['status']);
        }

        if (isset($filters['suspended']) && $filters['suspended'] !== '') {
            $builder->where('study_flashcards.suspended', (int) $filters['suspended']);
        }

        if (! empty($filters['flagged'])) {
            $builder->where('study_flashcards.flagged', 1);
        }

        if (! empty($filters['search'])) {
            $term = (string) $filters['search'];
            $builder->groupStart()
                ->like('study_flashcards.front', $term)
                ->orLike('study_flashcards.back', $term)
                ->groupEnd();
        }

        $total   = (clone $builder)->countAllResults(false);
        $perPage = max(1, min(100, $perPage));
        $page    = max(1, $page);

        $cards = $builder
            ->orderBy('study_flashcards.created_at', 'DESC')
            ->orderBy('study_flashcards.sort_order', 'ASC')
            ->findAll($perPage, ($page - 1) * $perPage);

        return [
            'cards'    => $cards,
            'total'    => $total,
            'page'     => $page,
            'pages'    => (int) max(1, ceil($total / $perPage)),
            'per_page' => $perPage,
        ];
    }

    /**
     * Disciplinas e assuntos disponíveis para vincular cartões.
     *
     * @return array{subjects:list<array<string,mixed>>, topics:list<array<string,mixed>>}
     */
    public function taxonomy(): array
    {
        $subjects = (new StudySubjectModel())
            ->where('active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();

        $topics = (new StudyTopicModel())
            ->where('active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();

        return ['subjects' => $subjects, 'topics' => $topics];
    }

    private function intOrNull($value): ?int
    {
        if ($value === null || $value === '' || (int) $value === 0) {
            return null;
        }

        return (int) $value;
    }
}
