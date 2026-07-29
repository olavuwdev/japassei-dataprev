<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\UserModel;
use App\Services\Flashcard\FlashcardApiTokenService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Gera um token da API externa pelo terminal. Útil para testes e para
 * recuperar o acesso quando a interface não está disponível.
 */
class CreateFlashcardToken extends BaseCommand
{
    protected $group       = 'Flashcards';
    protected $name        = 'flashcards:token';
    protected $description = 'Gera um token de integração da API externa de flashcards.';
    protected $usage       = 'flashcards:token --user 1 --name "ChatGPT" [--auto]';
    protected $options     = [
        '--user' => 'ID do usuário dono do token.',
        '--name' => 'Nome da integração.',
        '--auto' => 'Cadastrar automaticamente, sem exigir aprovação.',
    ];

    public function run(array $params): int
    {
        $userId = (int) ($params['user'] ?? CLI::getOption('user') ?? 0);
        $name   = (string) ($params['name'] ?? CLI::getOption('name') ?? 'Integração externa');

        if ($userId === 0) {
            $user = (new UserModel())->where('active', 1)->orderBy('id', 'ASC')->first();

            if ($user === null) {
                CLI::error('Nenhum usuário cadastrado.');

                return EXIT_ERROR;
            }

            $userId = (int) $user['id'];
        }

        try {
            $result = (new FlashcardApiTokenService())->create(
                $userId,
                $name,
                [FlashcardApiTokenService::SCOPE_IMPORT],
                ! CLI::getOption('auto')
            );
        } catch (Throwable $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        CLI::newLine();
        CLI::write('Token criado para o usuário #' . $userId . '.', 'green');
        CLI::write('Copie agora — ele não será exibido novamente:', 'yellow');
        CLI::newLine();
        CLI::write('  ' . $result['token'], 'light_cyan');
        CLI::newLine();
        CLI::write('Endpoint: ' . site_url('api/v1/flashcards/import'), 'dark_gray');

        return EXIT_SUCCESS;
    }
}
