<?php

declare(strict_types=1);

namespace App\Controllers\Flashcards;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Estatísticas de memorização e previsão de carga.
 */
class EstatisticasController extends BaseController
{
    public function index(): string
    {
        $stats  = service('flashcardStatistics');
        $userId = $this->userId();

        return view('flashcards/estatisticas', [
            'summary'      => $stats->dailySummary($userId),
            'subjects'     => $stats->bySubject($userId),
            'ratings'      => $stats->ratingDistribution($userId),
            'forecast'     => $stats->forecast($userId),
            'week'         => $stats->reviewsPerDay($userId, 14),
            'problematic'  => $stats->problematicCards($userId, 15),
            'aiUsage'      => $stats->aiUsage($userId),
        ]);
    }

    /**
     * Dados para os gráficos, carregados sob demanda.
     */
    public function data(): ResponseInterface
    {
        $stats  = service('flashcardStatistics');
        $userId = $this->userId();

        return $this->jsonResponse(true, [
            'ratings'  => $stats->ratingDistribution($userId, (int) ($this->request->getGet('dias') ?? 30)),
            'week'     => $stats->reviewsPerDay($userId, 14),
            'forecast' => $stats->forecast($userId),
        ]);
    }
}
