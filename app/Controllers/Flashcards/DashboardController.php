<?php

declare(strict_types=1);

namespace App\Controllers\Flashcards;

use App\Controllers\BaseController;

/**
 * Visão geral do módulo de flashcards.
 */
class DashboardController extends BaseController
{
    public function index(): string
    {
        $stats = service('flashcardStatistics');

        return view('flashcards/dashboard', [
            'summary'   => $stats->dailySummary($this->userId()),
            'subjects'  => $stats->bySubject($this->userId()),
            'activity'  => $stats->recentActivity($this->userId()),
        ]);
    }
}
