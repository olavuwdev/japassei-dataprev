<?php

declare(strict_types=1);

namespace App\Controllers\Estudos;

use App\Controllers\BaseController;
use App\Models\StudySubjectModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Indicadores, conquistas e gráficos de desempenho.
 */
class DesempenhoController extends BaseController
{
    public function index(): string
    {
        $userId  = $this->userId();
        $filters = $this->filters();

        $statistics = service('studyStatistics');

        return view('estudos/desempenho', [
            'filters'   => $filters,
            'overview'  => $statistics->getOverview($userId, $filters),
            'adherence' => service('studyPlan')->getAdherence($userId),
            'streak'    => service('studyStreak')->getState($userId),
            'level'     => service('studyProgress')->getLevelInfo($userId),
            'badges'    => service('studyProgress')->getUserBadges($userId),
            'subjects'  => (new StudySubjectModel())
                ->where('active', 1)
                ->orderBy('sort_order', 'ASC')
                ->orderBy('name', 'ASC')
                ->findAll(),
            'charts' => $this->chartData($userId, $filters),
        ]);
    }

    /**
     * Mesmos dados dos gráficos, em JSON, para atualização por filtro.
     * GET estudos/api/desempenho/dados?date_from=&date_to=&subject_id=&charts=a,b
     */
    public function data(): ResponseInterface
    {
        $charts = $this->chartData($this->userId(), $this->filters());

        $requested = trim((string) $this->request->getGet('charts'));
        if ($requested !== '') {
            $keys   = array_map('trim', explode(',', $requested));
            $charts = array_intersect_key($charts, array_flip($keys));
        }

        return $this->jsonResponse(true, ['charts' => $charts]);
    }

    // ------------------------------------------------------------------

    private function filters(): array
    {
        $filters = [];

        $dateFrom = (string) $this->request->getGet('date_from');
        $dateTo   = (string) $this->request->getGet('date_to');
        $subject  = (int) $this->request->getGet('subject_id');

        if ($dateFrom !== '' && strtotime($dateFrom) !== false) {
            $filters['date_from'] = $dateFrom;
        }
        if ($dateTo !== '' && strtotime($dateTo) !== false) {
            $filters['date_to'] = $dateTo;
        }
        if ($subject > 0) {
            $filters['subject_id'] = $subject;
        }

        return $filters;
    }

    private function chartData(int $userId, array $filters): array
    {
        $statistics = service('studyStatistics');

        return [
            'minutes_per_week'    => $statistics->minutesPerWeek($userId),
            'accuracy_by_subject' => $statistics->accuracyBySubject($userId, $filters),
            'accuracy_evolution'  => $statistics->accuracyEvolution($userId),
            'time_distribution'   => $statistics->timeDistribution($userId),
            'tasks_per_week'      => $statistics->tasksCompletedPerWeek($userId),
            'planned_vs_done'     => $statistics->plannedVsDone($userId),
        ];
    }
}
