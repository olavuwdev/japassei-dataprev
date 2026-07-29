<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Marca cartões problemáticos (§19) para que apareçam sinalizados na interface.
 */
class DetectProblematicCards extends BaseCommand
{
    protected $group       = 'Flashcards';
    protected $name        = 'flashcards:detect-problematic';
    protected $description = 'Sinaliza cartões esquecidos com frequência ou mal formulados.';
    protected $usage       = 'flashcards:detect-problematic';

    public function run(array $params): int
    {
        $db = Database::connect();

        // Esquecidos repetidamente.
        $db->query(
            'UPDATE study_flashcards c
             JOIN study_flashcard_states s ON s.flashcard_id = c.id AND s.user_id = c.user_id
             SET c.flagged = 1
             WHERE s.lapses >= 4 AND c.flagged = 0 AND c.deleted_at IS NULL'
        );
        $byLapses = $db->affectedRows();

        // Alta frequência de "Difícil".
        $db->query(
            'UPDATE study_flashcards c
             JOIN (SELECT flashcard_id,
                          SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) AS hard_count,
                          COUNT(*) AS total
                   FROM study_flashcard_reviews
                   WHERE undone = 0
                   GROUP BY flashcard_id
                   HAVING total >= 5) r ON r.flashcard_id = c.id
             SET c.flagged = 1
             WHERE r.hard_count / r.total >= 0.6 AND c.flagged = 0 AND c.deleted_at IS NULL'
        );
        $byHard = $db->affectedRows();

        // Resposta longa demais para um único cartão.
        $db->query(
            'UPDATE study_flashcards
             SET flagged = 1
             WHERE CHAR_LENGTH(back) >= 1500 AND flagged = 0 AND deleted_at IS NULL'
        );
        $byLength = $db->affectedRows();

        CLI::write('Sinalizados por esquecimento: ' . $byLapses, 'yellow');
        CLI::write('Sinalizados por dificuldade recorrente: ' . $byHard, 'yellow');
        CLI::write('Sinalizados por resposta longa: ' . $byLength, 'yellow');
        CLI::write('Concluído.', 'green');

        return EXIT_SUCCESS;
    }
}
