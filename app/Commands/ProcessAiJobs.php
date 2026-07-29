<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\FlashcardAiJobModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Processa gerações de IA pendentes. Útil quando o conteúdo é extenso demais
 * para caber no tempo de uma requisição web.
 */
class ProcessAiJobs extends BaseCommand
{
    protected $group       = 'Flashcards';
    protected $name        = 'flashcards:process-ai-jobs';
    protected $description = 'Processa as gerações de flashcards pendentes na fila da IA.';
    protected $usage       = 'flashcards:process-ai-jobs [--limit 10]';
    protected $options     = ['--limit' => 'Quantidade máxima de jobs por execução (padrão 10).'];

    public function run(array $params): int
    {
        $limit = (int) ($params['limit'] ?? CLI::getOption('limit') ?? 10);
        $model = new FlashcardAiJobModel();

        $jobs = $model
            ->where('status', FlashcardAiJobModel::STATUS_PENDING)
            ->where('attempts <', 3)
            ->orderBy('id', 'ASC')
            ->findAll(max(1, $limit));

        if ($jobs === []) {
            CLI::write('Nenhuma geração pendente.', 'yellow');

            return EXIT_SUCCESS;
        }

        $ok = 0;

        foreach ($jobs as $job) {
            CLI::write('Processando job #' . $job['id'] . '…');

            try {
                service('flashcardAi')->processJob((int) $job['id']);
                $ok++;
                CLI::write('  concluído.', 'green');
            } catch (Throwable $e) {
                CLI::write('  falhou: ' . $e->getMessage(), 'red');
            }
        }

        CLI::write($ok . ' de ' . count($jobs) . ' geração(ões) concluída(s).', 'green');

        return EXIT_SUCCESS;
    }
}
