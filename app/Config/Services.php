<?php

namespace Config;

use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    public static function studyPlan(bool $getShared = true): \App\Services\Study\StudyPlanService
    {
        return $getShared ? static::getSharedInstance('studyPlan') : new \App\Services\Study\StudyPlanService();
    }

    public static function studyTask(bool $getShared = true): \App\Services\Study\StudyTaskService
    {
        return $getShared ? static::getSharedInstance('studyTask') : new \App\Services\Study\StudyTaskService();
    }

    public static function studySession(bool $getShared = true): \App\Services\Study\StudySessionService
    {
        return $getShared ? static::getSharedInstance('studySession') : new \App\Services\Study\StudySessionService();
    }

    public static function studyStreak(bool $getShared = true): \App\Services\Study\StudyStreakService
    {
        return $getShared ? static::getSharedInstance('studyStreak') : new \App\Services\Study\StudyStreakService();
    }

    public static function studyReview(bool $getShared = true): \App\Services\Study\StudyReviewService
    {
        return $getShared ? static::getSharedInstance('studyReview') : new \App\Services\Study\StudyReviewService();
    }

    public static function studyProgress(bool $getShared = true): \App\Services\Study\StudyProgressService
    {
        return $getShared ? static::getSharedInstance('studyProgress') : new \App\Services\Study\StudyProgressService();
    }

    public static function studyStatistics(bool $getShared = true): \App\Services\Study\StudyStatisticsService
    {
        return $getShared ? static::getSharedInstance('studyStatistics') : new \App\Services\Study\StudyStatisticsService();
    }

    public static function studyKanban(bool $getShared = true): \App\Services\Study\StudyKanbanService
    {
        return $getShared ? static::getSharedInstance('studyKanban') : new \App\Services\Study\StudyKanbanService();
    }
}
