<?php

declare(strict_types=1);

namespace App\Controllers\Estudos;

use App\Controllers\BaseController;

/**
 * Cronograma do plano de estudos: 24 semanas com tarefas e adesão geral.
 */
class CronogramaController extends BaseController
{
    public function index(): string
    {
        $userId   = $this->userId();
        $plans    = service('studyPlan');
        $schedule = $plans->getSchedule($userId);

        return view('estudos/cronograma', [
            'plan'        => $schedule['plan'],
            'weeks'       => $schedule['weeks'],
            'adherence'   => $plans->getAdherence($userId),
            'currentWeek' => $plans->getWeekOf($userId),
        ]);
    }
}
