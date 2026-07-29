<?php

declare(strict_types=1);

namespace App\Services\Flashcard;

use App\Models\FlashcardSettingModel;
use Config\Database;

/**
 * Seleção e ordenação dos cartões de uma sessão (seção 18 do PRD).
 *
 * Prioridade: aprendizado vencido → reaprendizado → revisões vencidas
 * (menor recuperabilidade primeiro) → cartões novos, dentro do limite diário.
 */
class FlashcardQueueService
{
    /** Quantos cartões carregar por vez. */
    public const BATCH_SIZE = 50;

    /**
     * Cartões elegíveis para a sessão, já ordenados.
     *
     * @param array{subject_id?:?int, topic_id?:?int} $filters
     *
     * @return array{cards:list<array<string,mixed>>, counts:array{new:int, learning:int, review:int}}
     */
    public function build(int $userId, array $filters = []): array
    {
        $settings = (new FlashcardSettingModel())->forUser($userId);
        $nowUtc   = gmdate('Y-m-d H:i:s');

        $learning = $this->query($userId, $filters)
            ->whereIn('s.state', [FsrsClientService::STATE_LEARNING, FsrsClientService::STATE_RELEARNING])
            ->where('s.due <=', $nowUtc)
            ->orderBy('s.state', 'DESC')  // reaprendizado antes de aprendizado
            ->orderBy('s.due', 'ASC')
            ->get(self::BATCH_SIZE * 2)
            ->getResultArray();

        $reviewLimit = max(0, (int) $settings['reviews_per_day'] - $this->reviewsToday($userId));

        $due = $reviewLimit > 0
            ? $this->query($userId, $filters)
                ->where('s.state', FsrsClientService::STATE_REVIEW)
                ->where('s.due <=', $nowUtc)
                // Menor recuperabilidade primeiro ≈ maior atraso relativo à estabilidade.
                ->orderBy('(TIMESTAMPDIFF(SECOND, s.due, UTC_TIMESTAMP()) / GREATEST(s.stability, 0.1))', 'DESC', false)
                ->get(min($reviewLimit, self::BATCH_SIZE * 4))
                ->getResultArray()
            : [];

        $backlog     = count($due);
        $newAllowed  = max(0, (int) $settings['new_per_day'] - $this->newToday($userId));
        $threshold   = (int) $settings['backlog_threshold'];

        // Acúmulo grande de revisões: não introduzir cartões novos (§12.2).
        if ($threshold > 0 && $backlog >= $threshold) {
            $newAllowed = 0;
        }

        $new = $newAllowed > 0
            ? $this->query($userId, $filters)
                ->where('s.state', FsrsClientService::STATE_NEW)
                ->orderBy('c.sort_order', 'ASC')
                ->orderBy('c.id', 'ASC')
                ->get($newAllowed)
                ->getResultArray()
            : [];

        $ordered = $this->interleave($learning, $due, $new, (bool) $settings['shuffle_cards'], (bool) $settings['bury_siblings']);

        return [
            'cards'  => $ordered,
            'counts' => [
                'new'      => count($new),
                'learning' => count($learning),
                'review'   => count($due),
            ],
        ];
    }

    /**
     * Total de cartões vencidos agora, por estado — usado no dashboard.
     *
     * @return array{new:int, learning:int, review:int, total:int}
     */
    public function counts(int $userId, array $filters = []): array
    {
        $nowUtc = gmdate('Y-m-d H:i:s');

        $learning = (clone $this->query($userId, $filters))
            ->whereIn('s.state', [FsrsClientService::STATE_LEARNING, FsrsClientService::STATE_RELEARNING])
            ->where('s.due <=', $nowUtc)
            ->countAllResults();

        $review = (clone $this->query($userId, $filters))
            ->where('s.state', FsrsClientService::STATE_REVIEW)
            ->where('s.due <=', $nowUtc)
            ->countAllResults();

        $settings   = (new FlashcardSettingModel())->forUser($userId);
        $newAllowed = max(0, (int) $settings['new_per_day'] - $this->newToday($userId));

        $newAvailable = (clone $this->query($userId, $filters))
            ->where('s.state', FsrsClientService::STATE_NEW)
            ->countAllResults();

        $new = min($newAllowed, $newAvailable);

        return [
            'new'      => $new,
            'learning' => $learning,
            'review'   => $review,
            'total'    => $new + $learning + $review,
        ];
    }

    /**
     * Consulta base: apenas cartões ativos, não suspensos, na fila, do usuário.
     */
    private function query(int $userId, array $filters = [])
    {
        $builder = Database::connect()
            ->table('study_flashcard_states s')
            ->select('c.id, c.note_id, c.card_type, c.front, c.back, c.explanation, c.example,
                      c.source_excerpt, c.cloze_index, c.subject_id, c.topic_id, c.version,
                      s.id AS state_id, s.state, s.due, s.stability, s.difficulty, s.reps, s.lapses,
                      s.elapsed_days, s.scheduled_days, s.learning_step, s.last_review, s.version AS state_version')
            ->join('study_flashcards c', 'c.id = s.flashcard_id')
            ->where('s.user_id', $userId)
            ->where('s.in_queue', 1)
            ->where('c.suspended', 0)
            ->where('c.status', 'active')
            ->where('c.deleted_at', null);

        if (! empty($filters['subject_id'])) {
            $builder->where('c.subject_id', (int) $filters['subject_id']);
        }

        if (! empty($filters['topic_id'])) {
            $builder->where('c.topic_id', (int) $filters['topic_id']);
        }

        return $builder;
    }

    /**
     * Junta os três grupos preservando a prioridade, embaralhando equivalentes
     * e evitando cartões irmãos próximos.
     *
     * @param list<array<string,mixed>> $learning
     * @param list<array<string,mixed>> $due
     * @param list<array<string,mixed>> $new
     *
     * @return list<array<string,mixed>>
     */
    private function interleave(array $learning, array $due, array $new, bool $shuffle, bool $burySiblings): array
    {
        if ($shuffle) {
            shuffle($due);
            shuffle($new);
        }

        $ordered = array_merge($learning, $due, $new);

        return $burySiblings ? $this->spreadSiblings($ordered) : $ordered;
    }

    /**
     * Afasta cartões da mesma anotação para que não apareçam em sequência.
     *
     * @param list<array<string,mixed>> $cards
     *
     * @return list<array<string,mixed>>
     */
    private function spreadSiblings(array $cards): array
    {
        $result  = [];
        $pending = $cards;

        while ($pending !== []) {
            $picked = null;

            foreach ($pending as $index => $card) {
                $lastNote = $result === [] ? null : (int) $result[count($result) - 1]['note_id'];

                if ($lastNote === null || (int) $card['note_id'] !== $lastNote) {
                    $picked = $index;
                    break;
                }
            }

            // Só restam irmãos: mantém a ordem original.
            $picked ??= array_key_first($pending);

            $result[] = $pending[$picked];
            unset($pending[$picked]);
            $pending = array_values($pending);
        }

        return $result;
    }

    /**
     * Cartões novos já introduzidos hoje (no fuso do usuário, via UTC do dia).
     */
    private function newToday(int $userId): int
    {
        return Database::connect()
            ->table('study_flashcard_reviews')
            ->where('user_id', $userId)
            ->where('undone', 0)
            ->where('state_before', FsrsClientService::STATE_NEW)
            ->where('reviewed_at >=', gmdate('Y-m-d 00:00:00'))
            ->countAllResults();
    }

    private function reviewsToday(int $userId): int
    {
        return Database::connect()
            ->table('study_flashcard_reviews')
            ->where('user_id', $userId)
            ->where('undone', 0)
            ->where('state_before', FsrsClientService::STATE_REVIEW)
            ->where('reviewed_at >=', gmdate('Y-m-d 00:00:00'))
            ->countAllResults();
    }
}
