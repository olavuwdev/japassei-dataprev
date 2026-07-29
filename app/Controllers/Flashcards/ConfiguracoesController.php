<?php

declare(strict_types=1);

namespace App\Controllers\Flashcards;

use App\Controllers\BaseController;
use App\Models\FlashcardSettingModel;
use App\Services\Flashcard\FlashcardAuditService;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Flashcards as FlashcardsConfig;

/**
 * Configurações do usuário e painel administrativo do módulo.
 */
class ConfiguracoesController extends BaseController
{
    /** Limites seguros de retenção na interface (§21). */
    private const RETENTION_MIN = 0.80;
    private const RETENTION_MAX = 0.95;

    public function index(): string
    {
        $config = config(FlashcardsConfig::class);

        return view('flashcards/configuracoes', [
            'settings'  => (new FlashcardSettingModel())->forUser($this->userId()),
            'aiUsage'   => service('flashcardStatistics')->aiUsage($this->userId()),
            'openai'    => [
                'enabled'   => $config->aiEnabled,
                'model'     => $config->openAiModel,
                'fallback'  => $config->openAiFallbackModel,
                'key'       => $config->maskedApiKey(),
                'timeout'   => $config->openAiTimeout,
                'attempts'  => $config->openAiMaxAttempts,
                'maxCards'  => $config->maxCardsPerGeneration,
                'maxChars'  => $config->maxSourceChars,
                'daily'     => $config->aiDailyLimitPerUser,
                'monthly'   => $config->aiMonthlyCostLimit,
                'prompt'    => $config->promptVersion,
                'schema'    => $config->schemaVersion,
                'spent'     => service('aiUsage')->monthlyCost(),
            ],
            'fsrs' => array_merge(service('fsrs')->health(), [
                'url'      => $config->fsrsUrl,
                'token_set' => $config->fsrsToken !== '',
            ]),
        ]);
    }

    public function update(): ResponseInterface
    {
        $payload = $this->jsonPayload();
        $model   = new FlashcardSettingModel();
        $current = $model->forUser($this->userId());

        $retention = (float) ($payload['request_retention'] ?? $current['request_retention']);
        $retention = max(self::RETENTION_MIN, min(self::RETENTION_MAX, $retention));

        $data = [
            'request_retention'  => $retention,
            'maximum_interval'   => max(1, min(36500, (int) ($payload['maximum_interval'] ?? $current['maximum_interval']))),
            'new_per_day'        => max(0, min(9999, (int) ($payload['new_per_day'] ?? $current['new_per_day']))),
            'reviews_per_day'    => max(1, min(9999, (int) ($payload['reviews_per_day'] ?? $current['reviews_per_day']))),
            'learning_steps'     => $this->steps($payload['learning_steps'] ?? null, ['1m', '10m']),
            'relearning_steps'   => $this->steps($payload['relearning_steps'] ?? null, ['10m']),
            'enable_fuzz'        => $this->bool($payload['enable_fuzz'] ?? $current['enable_fuzz']),
            'enable_short_term'  => $this->bool($payload['enable_short_term'] ?? $current['enable_short_term']),
            'show_intervals'     => $this->bool($payload['show_intervals'] ?? $current['show_intervals']),
            'show_timer'         => $this->bool($payload['show_timer'] ?? $current['show_timer']),
            'keyboard_shortcuts' => $this->bool($payload['keyboard_shortcuts'] ?? $current['keyboard_shortcuts']),
            'shuffle_cards'      => $this->bool($payload['shuffle_cards'] ?? $current['shuffle_cards']),
            'bury_siblings'      => $this->bool($payload['bury_siblings'] ?? $current['bury_siblings']),
            'flip_animation'     => $this->bool($payload['flip_animation'] ?? $current['flip_animation']),
            'backlog_threshold'  => max(0, min(9999, (int) ($payload['backlog_threshold'] ?? $current['backlog_threshold']))),
        ];

        $model->update((int) $current['id'], $data);

        (new FlashcardAuditService())->log($this->userId(), FlashcardAuditService::SETTINGS_UPDATED, 'settings', (int) $current['id']);

        if ($this->request->isAJAX()) {
            return $this->jsonResponse(true, ['settings' => $model->find((int) $current['id'])], 'Configurações salvas.');
        }

        return redirect()->to('flashcards/configuracoes')->with('success', 'Configurações salvas.');
    }

    /**
     * Testa a conexão com o serviço FSRS.
     */
    public function testFsrs(): ResponseInterface
    {
        $health = service('fsrs')->health();

        return $this->jsonResponse(
            $health['online'],
            $health,
            $health['online']
                ? 'Serviço FSRS respondendo (ts-fsrs ' . ($health['version'] ?? '?') . ').'
                : 'Serviço FSRS indisponível: ' . ($health['message'] ?? 'sem resposta.')
        );
    }

    /**
     * Normaliza as etapas de aprendizado ("1m", "10m", "1d").
     *
     * @return string JSON pronto para gravação
     */
    private function steps($value, array $default): string
    {
        if (is_string($value)) {
            $value = preg_split('/[,\s]+/', trim($value)) ?: [];
        }

        if (! is_array($value)) {
            return json_encode($default);
        }

        $valid = [];

        foreach ($value as $step) {
            $step = strtolower(trim((string) $step));

            if (preg_match('/^\d+(m|h|d)$/', $step) === 1) {
                $valid[] = $step;
            }
        }

        return json_encode($valid === [] ? $default : array_slice($valid, 0, 8));
    }

    private function bool($value): int
    {
        return filter_var($value, FILTER_VALIDATE_BOOL) ? 1 : 0;
    }

}
