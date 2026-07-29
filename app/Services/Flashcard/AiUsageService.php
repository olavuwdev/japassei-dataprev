<?php

declare(strict_types=1);

namespace App\Services\Flashcard;

use Config\Database;
use Config\Flashcards as FlashcardsConfig;
use RuntimeException;

/**
 * Controle de consumo e custo da OpenAI (§14.11).
 */
class AiUsageService
{
    private FlashcardsConfig $config;

    public function __construct(?FlashcardsConfig $config = null)
    {
        $this->config = $config ?? config(FlashcardsConfig::class);
    }

    /**
     * Verifica se o usuário ainda pode disparar uma geração.
     *
     * @throws RuntimeException quando algum limite foi atingido
     */
    public function assertWithinLimits(int $userId): void
    {
        if (! $this->config->aiEnabled) {
            throw new RuntimeException('A geração por inteligência artificial está desativada.');
        }

        $today = $this->countToday($userId);

        if ($this->config->aiDailyLimitPerUser > 0 && $today >= $this->config->aiDailyLimitPerUser) {
            throw new RuntimeException(
                'Você atingiu o limite de ' . $this->config->aiDailyLimitPerUser . ' gerações por dia. Tente novamente amanhã.'
            );
        }

        if ($this->config->aiMonthlyCostLimit > 0 && $this->monthlyCost() >= $this->config->aiMonthlyCostLimit) {
            throw new RuntimeException('O limite mensal de custo da IA foi atingido. Fale com o administrador.');
        }
    }

    public function countToday(int $userId): int
    {
        return Database::connect()
            ->table('study_flashcard_ai_jobs')
            ->where('user_id', $userId)
            ->where('job_type', 'generate')
            ->where('created_at >=', gmdate('Y-m-d 00:00:00'))
            ->countAllResults();
    }

    public function remainingToday(int $userId): int
    {
        if ($this->config->aiDailyLimitPerUser <= 0) {
            return PHP_INT_MAX;
        }

        return max(0, $this->config->aiDailyLimitPerUser - $this->countToday($userId));
    }

    /**
     * Custo estimado acumulado no mês corrente, somando todos os usuários.
     */
    public function monthlyCost(): float
    {
        $row = Database::connect()
            ->table('study_flashcard_ai_jobs')
            ->selectSum('estimated_cost', 'total')
            ->where('created_at >=', gmdate('Y-m-01 00:00:00'))
            ->get()
            ->getRowArray();

        return round((float) ($row['total'] ?? 0), 4);
    }
}
