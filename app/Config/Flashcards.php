<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configuração administrativa do módulo de flashcards.
 *
 * Todos os valores podem ser sobrescritos por variáveis de ambiente, para que
 * modelo, limites e endereços mudem sem alteração de código-fonte.
 */
class Flashcards extends BaseConfig
{
    // ---------------------------------------------------------------- OpenAI

    /** Liga ou desliga a geração interna por IA. */
    public bool $aiEnabled = true;

    public string $openAiApiKey = '';

    public string $openAiProjectId = '';

    /** Modelo econômico com suporte a Structured Outputs. */
    public string $openAiModel = 'gpt-4.1-mini';

    public string $openAiFallbackModel = 'gpt-4.1-nano';

    public string $openAiBaseUrl = 'https://api.openai.com/v1';

    public float $openAiTemperature = 0.2;

    public int $openAiMaxOutputTokens = 8000;

    public int $openAiTimeout = 120;

    public int $openAiMaxAttempts = 3;

    /** Preço por 1M de tokens (USD) — usado apenas para estimar custo. */
    public float $openAiInputPricePerMillion = 0.40;

    public float $openAiOutputPricePerMillion = 1.60;

    public string $promptVersion = '1.0';

    public string $schemaVersion = '1.0';

    // --------------------------------------------------------------- Limites

    /** Máximo de cartões por geração. */
    public int $maxCardsPerGeneration = 30;

    /** Máximo de caracteres aceitos de uma fonte. */
    public int $maxSourceChars = 60000;

    /** Tamanho de cada bloco enviado à IA quando o conteúdo é dividido. */
    public int $chunkChars = 12000;

    /** Gerações de IA permitidas por usuário por dia. */
    public int $aiDailyLimitPerUser = 20;

    /** Teto de custo mensal estimado, em USD. 0 desliga a verificação. */
    public float $aiMonthlyCostLimit = 0.0;

    // ------------------------------------------------------------------ FSRS

    /** Endereço interno do serviço Node (não deve ser exposto à internet). */
    public string $fsrsUrl = 'http://127.0.0.1:3100';

    /** Token compartilhado com o serviço FSRS. */
    public string $fsrsToken = '';

    public int $fsrsTimeout = 10;

    // ----------------------------------------------------- Extração de links

    public int $fetchTimeout = 15;

    public int $fetchMaxBytes = 3000000;

    public int $fetchMaxRedirects = 3;

    // ------------------------------------------------------------ API externa

    public int $apiMaxCardsPerRequest = 100;

    public int $apiMaxQuestionChars = 2000;

    public int $apiMaxAnswerChars = 10000;

    public int $apiMaxTagsPerCard = 20;

    public int $apiRequestsPerMinute = 30;

    public int $apiDailyImportLimit = 200;

    public function __construct()
    {
        parent::__construct();

        $this->aiEnabled    = $this->boolEnv('flashcards.aiEnabled', $this->aiEnabled);
        $this->openAiApiKey = (string) (env('OPENAI_API_KEY') ?? $this->openAiApiKey);
        $this->openAiProjectId = (string) (env('OPENAI_PROJECT_ID') ?? $this->openAiProjectId);
        $this->openAiModel  = (string) (env('OPENAI_MODEL_FLASHCARDS') ?: $this->openAiModel);
        $this->openAiFallbackModel = (string) (env('OPENAI_MODEL_FLASHCARDS_FALLBACK') ?: $this->openAiFallbackModel);

        $this->fsrsUrl   = rtrim((string) (env('FSRS_SERVICE_URL') ?: $this->fsrsUrl), '/');
        $this->fsrsToken = (string) (env('FSRS_SERVICE_TOKEN') ?? $this->fsrsToken);
    }

    private function boolEnv(string $key, bool $default): bool
    {
        $value = env($key);

        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    /**
     * Máscara segura da chave para exibição administrativa.
     */
    public function maskedApiKey(): string
    {
        if ($this->openAiApiKey === '') {
            return 'não configurada';
        }

        $prefix = substr($this->openAiApiKey, 0, 7);
        $suffix = substr($this->openAiApiKey, -4);

        return $prefix . str_repeat('•', 12) . $suffix;
    }
}
