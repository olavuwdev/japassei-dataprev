<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\FlashcardStateModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Throwable;

/**
 * Reconstrói o estado FSRS dos cartões reaplicando todo o histórico.
 * Necessário depois de mudanças relevantes de parâmetros.
 */
class RebuildFsrs extends BaseCommand
{
    protected $group       = 'Flashcards';
    protected $name        = 'flashcards:rebuild-fsrs';
    protected $description = 'Reconstrói o estado FSRS a partir do histórico de revisões.';
    protected $usage       = 'flashcards:rebuild-fsrs --user 1 [--dry-run]';
    protected $options     = [
        '--user'    => 'ID do usuário (obrigatório).',
        '--dry-run' => 'Apenas mostra o que seria alterado.',
    ];

    public function run(array $params): int
    {
        $userId = (int) ($params['user'] ?? CLI::getOption('user') ?? 0);
        $dryRun = (bool) (CLI::getOption('dry-run') ?? false);

        if ($userId <= 0) {
            CLI::error('Informe o usuário: --user 1');

            return EXIT_ERROR;
        }

        $fsrs  = service('fsrs');
        $db    = Database::connect();
        $model = new FlashcardStateModel();

        $states = $model->where('user_id', $userId)->where('reps >', 0)->findAll();

        if ($states === []) {
            CLI::write('Nenhum cartão com histórico para este usuário.', 'yellow');

            return EXIT_SUCCESS;
        }

        CLI::write(count($states) . ' cartão(ões) a reconstruir.');

        $updated = 0;

        foreach ($states as $state) {
            $history = $db->table('study_flashcard_reviews')
                ->select('rating, reviewed_at AS review')
                ->where('flashcard_id', (int) $state['flashcard_id'])
                ->where('user_id', $userId)
                ->where('undone', 0)
                ->orderBy('reviewed_at', 'ASC')
                ->get()
                ->getResultArray();

            if ($history === []) {
                continue;
            }

            try {
                $card = $fsrs->rebuild($history, $userId);
            } catch (Throwable $e) {
                CLI::write('  cartão ' . $state['flashcard_id'] . ': ' . $e->getMessage(), 'red');
                continue;
            }

            if ($dryRun) {
                CLI::write('  cartão ' . $state['flashcard_id'] . ': due ' . $state['due'] . ' → ' . ($card['due'] ?? '?'));
                continue;
            }

            $model->update((int) $state['id'], array_merge($fsrs->toDatabaseColumns($card), [
                'version' => (int) $state['version'] + 1,
            ]));

            $updated++;
        }

        CLI::write($dryRun ? 'Simulação concluída.' : $updated . ' cartão(ões) atualizado(s).', 'green');

        return EXIT_SUCCESS;
    }
}
