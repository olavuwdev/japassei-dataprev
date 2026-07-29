<?php

declare(strict_types=1);

namespace App\Controllers\Flashcards;

use App\Controllers\BaseController;
use App\Models\FlashcardAiJobModel;
use App\Models\FlashcardAiSuggestionModel;
use App\Models\FlashcardSourceModel;
use App\Services\Flashcard\OpenAiRefusalException;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Flashcards as FlashcardsConfig;
use RuntimeException;
use Throwable;

/**
 * Geração de cartões pela OpenAI e tela de aprovação das sugestões.
 */
class IaController extends BaseController
{
    public function new(): string
    {
        $config = config(FlashcardsConfig::class);

        return view('flashcards/gerar', [
            'taxonomy'  => service('flashcard')->taxonomy(),
            'enabled'   => $config->aiEnabled && $config->openAiApiKey !== '',
            'remaining' => service('aiUsage')->remainingToday($this->userId()),
            'maxCards'  => $config->maxCardsPerGeneration,
        ]);
    }

    /**
     * Cria a fonte e o job. O processamento acontece na sequência; quando o
     * conteúdo é extenso, o cliente acompanha o status pela rota de polling.
     */
    public function createJob(): ResponseInterface
    {
        try {
            $job = service('flashcardAi')->createJob($this->userId(), $this->jsonPayload());
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }

        return $this->jsonResponse(true, ['job' => $this->presentJob($job)], 'Conteúdo recebido. Gerando os flashcards…');
    }

    /**
     * Executa o job. Chamada separada para que a tela já mostre o progresso.
     */
    public function process(string $uuid): ResponseInterface
    {
        $job = $this->findJob($uuid);

        if ($job === null) {
            return $this->jsonResponse(false, [], 'Geração não encontrada.', 404);
        }

        try {
            $job = service('flashcardAi')->processJob((int) $job['id']);

            return $this->jsonResponse(true, ['job' => $this->presentJob($job)]);
        } catch (OpenAiRefusalException $e) {
            return $this->jsonResponse(false, ['refusal' => true], 'A IA recusou-se a processar este conteúdo: ' . $e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->jsonResponse(
                false,
                [],
                $e->getMessage() !== '' ? $e->getMessage() : 'Não foi possível gerar os flashcards neste momento. O conteúdo foi salvo e poderá ser processado novamente.',
                502
            );
        }
    }

    public function status(string $uuid): ResponseInterface
    {
        $job = $this->findJob($uuid);

        if ($job === null) {
            return $this->jsonResponse(false, [], 'Geração não encontrada.', 404);
        }

        return $this->jsonResponse(true, ['job' => $this->presentJob($job)]);
    }

    public function result(string $uuid): ResponseInterface
    {
        $job = $this->findJob($uuid);

        if ($job === null) {
            return $this->jsonResponse(false, [], 'Geração não encontrada.', 404);
        }

        $suggestions = (new FlashcardAiSuggestionModel())
            ->where('user_id', $this->userId())
            ->where('job_id', (int) $job['id'])
            ->where('status', FlashcardAiSuggestionModel::STATUS_PENDING)
            ->orderBy('id', 'ASC')
            ->findAll();

        return $this->jsonResponse(true, [
            'job'         => $this->presentJob($job),
            'suggestions' => $suggestions,
        ]);
    }

    public function approve(string $uuid): ResponseInterface
    {
        $payload = $this->jsonPayload();
        $ids     = array_map('intval', (array) ($payload['ids'] ?? []));

        if ($ids === []) {
            return $this->jsonResponse(false, [], 'Selecione ao menos um cartão para salvar.', 422);
        }

        try {
            $result = service('flashcardAi')->approve($this->userId(), $uuid, $ids, (array) ($payload['edits'] ?? []));

            $message = $result['created'] . ' cartão(ões) salvo(s).';

            if ($result['duplicates'] > 0) {
                $message .= ' ' . $result['duplicates'] . ' duplicado(s) foram ignorados.';
            }

            return $this->jsonResponse(true, $result, $message);
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }

    public function reject(string $uuid): ResponseInterface
    {
        $ids = array_map('intval', (array) ($this->jsonPayload()['ids'] ?? []));

        $count = service('flashcardAi')->reject($this->userId(), $ids, (string) ($this->jsonPayload()['reason'] ?? ''));

        return $this->jsonResponse(true, ['rejected' => $count], $count . ' sugestão(ões) descartada(s).');
    }

    /**
     * Reprocessa uma geração que falhou, sem exigir novo envio do conteúdo.
     */
    public function retry(string $uuid): ResponseInterface
    {
        $job = $this->findJob($uuid);

        if ($job === null) {
            return $this->jsonResponse(false, [], 'Geração não encontrada.', 404);
        }

        try {
            service('aiUsage')->assertWithinLimits($this->userId());

            (new FlashcardAiJobModel())->update((int) $job['id'], [
                'status'        => FlashcardAiJobModel::STATUS_PENDING,
                'error_message' => null,
            ]);

            (new FlashcardAiSuggestionModel())
                ->where('job_id', (int) $job['id'])
                ->where('status', FlashcardAiSuggestionModel::STATUS_PENDING)
                ->delete();

            $job = service('flashcardAi')->processJob((int) $job['id']);

            return $this->jsonResponse(true, ['job' => $this->presentJob($job)], 'Geração refeita.');
        } catch (Throwable $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 502);
        }
    }

    /**
     * Pede à IA uma versão melhor de um cartão problemático (§19).
     */
    public function improve(int $cardId): ResponseInterface
    {
        try {
            $result = service('flashcardAi')->improveCard($this->userId(), $cardId);

            return $this->jsonResponse(true, [
                'job'   => $this->presentJob($result['job']),
                'count' => $result['count'],
            ], $result['count'] . ' sugestão(ões) gerada(s). Revise antes de aplicar.');
        } catch (Throwable $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 502);
        }
    }

    /**
     * Lista as fontes de estudo já processadas.
     */
    public function sources(): string
    {
        $sources = (new FlashcardSourceModel())
            ->select('study_flashcard_sources.*, study_subjects.name AS subject_name')
            ->join('study_subjects', 'study_subjects.id = study_flashcard_sources.subject_id', 'left')
            ->where('study_flashcard_sources.user_id', $this->userId())
            ->orderBy('study_flashcard_sources.id', 'DESC')
            ->findAll(100);

        return view('flashcards/fontes', ['sources' => $sources]);
    }

    // ------------------------------------------------------------ Auxiliares

    /**
     * @return array<string, mixed>|null
     */
    private function findJob(string $uuid): ?array
    {
        return (new FlashcardAiJobModel())
            ->where('user_id', $this->userId())
            ->where('uuid', $uuid)
            ->first();
    }

    /**
     * Nunca expõe chaves nem detalhes internos ao navegador.
     *
     * @param array<string, mixed> $job
     *
     * @return array<string, mixed>
     */
    private function presentJob(array $job): array
    {
        return [
            'uuid'        => $job['uuid'],
            'status'      => $job['status'],
            'status_label' => FlashcardAiJobModel::STATUS_LABELS[$job['status']] ?? $job['status'],
            'stage'       => $job['stage'],
            'attempts'    => (int) $job['attempts'],
            'cards'       => (new FlashcardAiSuggestionModel())
                ->where('job_id', (int) $job['id'])
                ->where('status', FlashcardAiSuggestionModel::STATUS_PENDING)
                ->countAllResults(),
            'warnings'    => json_decode((string) ($job['warnings'] ?? ''), true) ?: [],
            'error'       => $job['error_message'],
        ];
    }

}
