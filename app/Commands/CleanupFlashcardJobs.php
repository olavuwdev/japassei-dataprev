<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Remove sugestões antigas nunca aprovadas e jobs com falha, mantendo o
 * histórico de consumo da IA.
 */
class CleanupFlashcardJobs extends BaseCommand
{
    protected $group       = 'Flashcards';
    protected $name        = 'flashcards:cleanup-jobs';
    protected $description = 'Limpa sugestões de IA antigas que nunca foram aprovadas.';
    protected $usage       = 'flashcards:cleanup-jobs [--days 30]';
    protected $options     = ['--days' => 'Idade mínima em dias (padrão 30).'];

    public function run(array $params): int
    {
        $days   = max(1, (int) ($params['days'] ?? CLI::getOption('days') ?? 30));
        $cutoff = gmdate('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
        $db     = Database::connect();

        $db->table('study_flashcard_ai_suggestions')
            ->where('status', 'pending')
            ->where('created_at <', $cutoff)
            ->delete();

        $suggestions = $db->affectedRows();

        // Fontes órfãs cujo processamento falhou e nunca gerou cartões.
        $db->table('study_flashcard_sources')
            ->where('status', 'error')
            ->where('cards_count', 0)
            ->where('created_at <', $cutoff)
            ->delete();

        $sources = $db->affectedRows();

        CLI::write($suggestions . ' sugestão(ões) removida(s).', 'green');
        CLI::write($sources . ' fonte(s) com falha removida(s).', 'green');

        return EXIT_SUCCESS;
    }
}
