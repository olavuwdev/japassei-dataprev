<?php

declare(strict_types=1);

namespace App\Controllers\Estudos;

use App\Controllers\BaseController;

/**
 * Página "Hoje": só o essencial — o que estudar, checklist, timer,
 * revisões pendentes e ofensiva (seção 19 do plano).
 */
class HojeController extends BaseController
{
    public function index(): string
    {
        $userId = $this->userId();

        /** @var \App\Services\Study\StudyTaskService $tasks */
        $tasks = service('studyTask');

        $mainTask = $tasks->getMainTask($userId);
        $dayTasks = $tasks->getTasksForDate($userId);

        $otherTasks = $mainTask !== null
            ? array_values(array_filter(
                $dayTasks,
                static fn (array $task): bool => (int) $task['id'] !== (int) $mainTask['id']
            ))
            : $dayTasks;

        $reviews = service('studyReview')->getGrouped($userId);

        return view('estudos/hoje', [
            'mainTask'       => $mainTask,
            'otherTasks'     => $otherTasks,
            'nextTask'       => $mainTask === null ? $tasks->getNextUpcomingTask($userId) : null,
            'activeSession'  => service('studySession')->getActive($userId),
            'reviewsToday'   => $reviews['today'],
            'reviewsOverdue' => $reviews['overdue'],
            'streak'         => service('studyStreak')->getState($userId),
            'daily'          => service('studyProgress')->getOrCreateDaily($userId),
            'todayDate'      => date('Y-m-d'),
        ]);
    }
}
