<?php

declare(strict_types=1);

namespace App\Services\Flashcard;

use Config\Database;

/**
 * Números do dashboard, da tela de estatísticas e da detecção de cartões
 * problemáticos.
 */
class FlashcardStatisticsService
{
    public function __construct(private ?FlashcardQueueService $queue = null)
    {
        $this->queue ??= new FlashcardQueueService();
    }

    /**
     * Resumo diário do dashboard (§9.2).
     *
     * @return array<string, mixed>
     */
    public function dailySummary(int $userId): array
    {
        $db     = Database::connect();
        $counts = $this->queue->counts($userId);
        $today  = gmdate('Y-m-d 00:00:00');

        $reviewedToday = $db->table('study_flashcard_reviews')
            ->where('user_id', $userId)
            ->where('undone', 0)
            ->where('reviewed_at >=', $today)
            ->countAllResults();

        $recall = $db->table('study_flashcard_reviews')
            ->select('COUNT(*) AS total, SUM(CASE WHEN rating > 1 THEN 1 ELSE 0 END) AS remembered')
            ->where('user_id', $userId)
            ->where('undone', 0)
            ->where('reviewed_at >=', gmdate('Y-m-d 00:00:00', strtotime('-30 days')))
            ->get()
            ->getRowArray() ?? ['total' => 0, 'remembered' => 0];

        $totalCards = $db->table('study_flashcards')
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->where('status', 'active')
            ->countAllResults();

        return [
            'due_reviews'    => $counts['review'],
            'new_available'  => $counts['new'],
            'learning'       => $counts['learning'],
            'total_due'      => $counts['total'],
            'reviewed_today' => $reviewedToday,
            'streak_days'    => $this->streak($userId),
            'recall_rate'    => (int) $recall['total'] > 0
                ? round(((int) $recall['remembered'] / (int) $recall['total']) * 100, 1)
                : null,
            'total_cards'    => $totalCards,
        ];
    }

    /**
     * Sequência de dias consecutivos com pelo menos uma revisão.
     */
    public function streak(int $userId): int
    {
        $rows = Database::connect()
            ->table('study_flashcard_reviews')
            ->select('DATE(reviewed_at) AS day', false)
            ->where('user_id', $userId)
            ->where('undone', 0)
            ->groupBy('day')
            ->orderBy('day', 'DESC')
            ->get(400)
            ->getResultArray();

        if ($rows === []) {
            return 0;
        }

        $days   = array_column($rows, 'day');
        $cursor = gmdate('Y-m-d');

        // A sequência continua valendo se a última revisão foi ontem.
        if ($days[0] !== $cursor) {
            $cursor = gmdate('Y-m-d', strtotime('-1 day'));

            if ($days[0] !== $cursor) {
                return 0;
            }
        }

        $streak = 0;

        foreach ($days as $day) {
            if ($day !== $cursor) {
                break;
            }

            $streak++;
            $cursor = gmdate('Y-m-d', strtotime($cursor . ' -1 day'));
        }

        return $streak;
    }

    /**
     * Desempenho por disciplina (§9.3 e §20.2).
     *
     * @return list<array<string, mixed>>
     */
    public function bySubject(int $userId): array
    {
        $db     = Database::connect();
        $nowUtc = gmdate('Y-m-d H:i:s');

        $rows = $db->table('study_flashcards c')
            ->select("COALESCE(s.id, 0) AS subject_id,
                      COALESCE(s.name, 'Sem disciplina') AS subject_name,
                      COUNT(*) AS total_cards,
                      SUM(CASE WHEN st.state = 0 THEN 1 ELSE 0 END) AS new_cards,
                      SUM(CASE WHEN st.due <= '{$nowUtc}' AND st.state > 0 AND st.in_queue = 1 AND c.suspended = 0 THEN 1 ELSE 0 END) AS due_cards,
                      SUM(CASE WHEN c.suspended = 1 THEN 1 ELSE 0 END) AS suspended_cards,
                      SUM(CASE WHEN c.flagged = 1 THEN 1 ELSE 0 END) AS flagged_cards,
                      MAX(st.last_review) AS last_review", false)
            ->join('study_flashcard_states st', 'st.flashcard_id = c.id AND st.user_id = c.user_id', 'left')
            ->join('study_subjects s', 's.id = c.subject_id', 'left')
            ->where('c.user_id', $userId)
            ->where('c.deleted_at', null)
            ->where('c.status', 'active')
            ->groupBy('subject_id, subject_name')
            ->orderBy('total_cards', 'DESC')
            ->get()
            ->getResultArray();

        $recall = $db->table('study_flashcard_reviews r')
            ->select('COALESCE(c.subject_id, 0) AS subject_id, COUNT(*) AS total,
                      SUM(CASE WHEN r.rating > 1 THEN 1 ELSE 0 END) AS remembered', false)
            ->join('study_flashcards c', 'c.id = r.flashcard_id')
            ->where('r.user_id', $userId)
            ->where('r.undone', 0)
            ->groupBy('subject_id')
            ->get()
            ->getResultArray();

        $recallBySubject = [];
        foreach ($recall as $row) {
            $total = (int) $row['total'];

            $recallBySubject[(int) $row['subject_id']] = $total > 0
                ? round(((int) $row['remembered'] / $total) * 100, 1)
                : null;
        }

        foreach ($rows as &$row) {
            $row['retention'] = $recallBySubject[(int) $row['subject_id']] ?? null;
        }

        return $rows;
    }

    /**
     * Distribuição das avaliações no período (§20.3).
     *
     * @return array{again:int, hard:int, good:int, easy:int}
     */
    public function ratingDistribution(int $userId, int $days = 30): array
    {
        $row = Database::connect()
            ->table('study_flashcard_reviews')
            ->select('SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) AS again,
                      SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) AS hard,
                      SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) AS good,
                      SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) AS easy', false)
            ->where('user_id', $userId)
            ->where('undone', 0)
            ->where('reviewed_at >=', gmdate('Y-m-d 00:00:00', strtotime('-' . $days . ' days')))
            ->get()
            ->getRowArray() ?? [];

        return [
            'again' => (int) ($row['again'] ?? 0),
            'hard'  => (int) ($row['hard'] ?? 0),
            'good'  => (int) ($row['good'] ?? 0),
            'easy'  => (int) ($row['easy'] ?? 0),
        ];
    }

    /**
     * Revisões por dia nos últimos N dias.
     *
     * @return list<array{day:string, total:int}>
     */
    public function reviewsPerDay(int $userId, int $days = 7): array
    {
        $rows = Database::connect()
            ->table('study_flashcard_reviews')
            ->select('DATE(reviewed_at) AS day, COUNT(*) AS total', false)
            ->where('user_id', $userId)
            ->where('undone', 0)
            ->where('reviewed_at >=', gmdate('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days')))
            ->groupBy('day')
            ->orderBy('day', 'ASC')
            ->get()
            ->getResultArray();

        $byDay = array_column($rows, 'total', 'day');
        $out   = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $day   = gmdate('Y-m-d', strtotime('-' . $i . ' days'));
            $out[] = ['day' => $day, 'total' => (int) ($byDay[$day] ?? 0)];
        }

        return $out;
    }

    /**
     * Previsão de carga: quantos cartões vencem amanhã, em 7 e em 30 dias (§20.4).
     *
     * @return array{tomorrow:int, week:int, month:int, daily:list<array{day:string,total:int}>}
     */
    public function forecast(int $userId): array
    {
        $rows = Database::connect()
            ->table('study_flashcard_states s')
            ->select('DATE(s.due) AS day, COUNT(*) AS total', false)
            ->join('study_flashcards c', 'c.id = s.flashcard_id')
            ->where('s.user_id', $userId)
            ->where('s.in_queue', 1)
            ->where('c.suspended', 0)
            ->where('c.deleted_at', null)
            ->where('s.state >', 0)
            ->where('s.due >', gmdate('Y-m-d 23:59:59'))
            ->where('s.due <=', gmdate('Y-m-d 23:59:59', strtotime('+30 days')))
            ->groupBy('day')
            ->orderBy('day', 'ASC')
            ->get()
            ->getResultArray();

        $byDay    = array_column($rows, 'total', 'day');
        $tomorrow = (int) ($byDay[gmdate('Y-m-d', strtotime('+1 day'))] ?? 0);

        $week  = 0;
        $month = 0;
        $daily = [];

        for ($i = 1; $i <= 30; $i++) {
            $day   = gmdate('Y-m-d', strtotime('+' . $i . ' days'));
            $total = (int) ($byDay[$day] ?? 0);

            $month += $total;
            if ($i <= 7) {
                $week += $total;
                $daily[] = ['day' => $day, 'total' => $total];
            }
        }

        return ['tomorrow' => $tomorrow, 'week' => $week, 'month' => $month, 'daily' => $daily];
    }

    /**
     * Atividade recente do dashboard (§9.4).
     *
     * @return array<string, mixed>
     */
    public function recentActivity(int $userId): array
    {
        $db = Database::connect();

        $sources = $db->table('study_flashcard_sources')
            ->select('id, title, source_type, status, cards_count, created_at')
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->orderBy('id', 'DESC')
            ->get(5)
            ->getResultArray();

        $cards = $db->table('study_flashcards c')
            ->select('c.id, c.front, c.card_type, c.created_at, s.name AS subject_name')
            ->join('study_subjects s', 's.id = c.subject_id', 'left')
            ->where('c.user_id', $userId)
            ->where('c.deleted_at', null)
            ->orderBy('c.id', 'DESC')
            ->get(5)
            ->getResultArray();

        return [
            'sources'   => $sources,
            'cards'     => $cards,
            'forgotten' => $this->problematicCards($userId, 5),
            'week'      => $this->reviewsPerDay($userId, 7),
        ];
    }

    /**
     * Cartões problemáticos (§19): esquecidos com frequência, muito marcados
     * como difíceis, resposta longa demais, muitas edições ou baixa confiança.
     *
     * @return list<array<string, mixed>>
     */
    public function problematicCards(int $userId, int $limit = 20): array
    {
        return Database::connect()
            ->table('study_flashcards c')
            ->select("c.id, c.front, c.back, c.card_type, c.flagged, c.edit_count, c.ai_confidence,
                      st.lapses, st.reps, sub.name AS subject_name,
                      COALESCE(agg.again_count, 0) AS again_count,
                      COALESCE(agg.hard_count, 0) AS hard_count,
                      CHAR_LENGTH(c.back) AS answer_length", false)
            ->join('study_flashcard_states st', 'st.flashcard_id = c.id AND st.user_id = c.user_id', 'left')
            ->join('study_subjects sub', 'sub.id = c.subject_id', 'left')
            ->join(
                '(SELECT flashcard_id,
                         SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) AS again_count,
                         SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) AS hard_count
                  FROM study_flashcard_reviews WHERE undone = 0 GROUP BY flashcard_id) agg',
                'agg.flashcard_id = c.id',
                'left'
            )
            ->where('c.user_id', $userId)
            ->where('c.deleted_at', null)
            ->groupStart()
                ->where('st.lapses >=', 3)
                ->orWhere('c.flagged', 1)
                ->orWhere('c.edit_count >=', 5)
                ->orWhere('CHAR_LENGTH(c.back) >=', 1200)
                ->orGroupStart()
                    ->where('c.ai_confidence IS NOT NULL')
                    ->where('c.ai_confidence <', 0.6)
                ->groupEnd()
            ->groupEnd()
            ->orderBy('st.lapses', 'DESC')
            ->get($limit)
            ->getResultArray();
    }

    /**
     * Histórico de revisões paginado (§8 "Histórico").
     *
     * @return array{items:list<array<string,mixed>>, total:int, page:int, pages:int}
     */
    public function reviewHistory(int $userId, int $page = 1, int $perPage = 30): array
    {
        $db = Database::connect();

        $base = $db->table('study_flashcard_reviews r')
            ->join('study_flashcards c', 'c.id = r.flashcard_id')
            ->join('study_subjects s', 's.id = c.subject_id', 'left')
            ->where('r.user_id', $userId);

        $total = (clone $base)->countAllResults(false);

        $items = $base
            ->select('r.id, r.rating, r.reviewed_at, r.undone, r.state_before, r.state_after,
                      r.due_after, c.id AS card_id, c.front, c.card_type, s.name AS subject_name')
            ->orderBy('r.id', 'DESC')
            ->get($perPage, ($page - 1) * $perPage)
            ->getResultArray();

        return [
            'items' => $items,
            'total' => $total,
            'page'  => $page,
            'pages' => (int) max(1, ceil($total / $perPage)),
        ];
    }

    /**
     * Consumo da IA para o administrador (§20.5).
     *
     * @return array<string, mixed>
     */
    public function aiUsage(int $userId, ?int $days = 30): array
    {
        $row = Database::connect()
            ->table('study_flashcard_ai_jobs')
            ->select('COUNT(*) AS requests,
                      SUM(COALESCE(input_tokens,0)) AS input_tokens,
                      SUM(COALESCE(output_tokens,0)) AS output_tokens,
                      SUM(COALESCE(estimated_cost,0)) AS cost,
                      AVG(COALESCE(duration_ms,0)) AS avg_ms,
                      SUM(CASE WHEN status = "error" THEN 1 ELSE 0 END) AS failures', false)
            ->where('user_id', $userId)
            ->where('created_at >=', gmdate('Y-m-d 00:00:00', strtotime('-' . $days . ' days')))
            ->get()
            ->getRowArray() ?? [];

        return [
            'requests'      => (int) ($row['requests'] ?? 0),
            'input_tokens'  => (int) ($row['input_tokens'] ?? 0),
            'output_tokens' => (int) ($row['output_tokens'] ?? 0),
            'cost'          => round((float) ($row['cost'] ?? 0), 4),
            'avg_ms'        => (int) round((float) ($row['avg_ms'] ?? 0)),
            'failures'      => (int) ($row['failures'] ?? 0),
        ];
    }
}
