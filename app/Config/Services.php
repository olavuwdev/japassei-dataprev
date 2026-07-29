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

    // ------------------------------------------------------------ Flashcards

    public static function flashcard(bool $getShared = true): \App\Services\Flashcard\FlashcardService
    {
        return $getShared ? static::getSharedInstance('flashcard') : new \App\Services\Flashcard\FlashcardService();
    }

    public static function flashcardSession(bool $getShared = true): \App\Services\Flashcard\FlashcardSessionService
    {
        return $getShared ? static::getSharedInstance('flashcardSession') : new \App\Services\Flashcard\FlashcardSessionService();
    }

    public static function flashcardQueue(bool $getShared = true): \App\Services\Flashcard\FlashcardQueueService
    {
        return $getShared ? static::getSharedInstance('flashcardQueue') : new \App\Services\Flashcard\FlashcardQueueService();
    }

    public static function flashcardStatistics(bool $getShared = true): \App\Services\Flashcard\FlashcardStatisticsService
    {
        return $getShared ? static::getSharedInstance('flashcardStatistics') : new \App\Services\Flashcard\FlashcardStatisticsService();
    }

    public static function flashcardAi(bool $getShared = true): \App\Services\Flashcard\FlashcardAiService
    {
        return $getShared ? static::getSharedInstance('flashcardAi') : new \App\Services\Flashcard\FlashcardAiService();
    }

    public static function flashcardImport(bool $getShared = true): \App\Services\Flashcard\FlashcardApiImportService
    {
        return $getShared ? static::getSharedInstance('flashcardImport') : new \App\Services\Flashcard\FlashcardApiImportService();
    }

    public static function flashcardToken(bool $getShared = true): \App\Services\Flashcard\FlashcardApiTokenService
    {
        return $getShared ? static::getSharedInstance('flashcardToken') : new \App\Services\Flashcard\FlashcardApiTokenService();
    }

    public static function fsrs(bool $getShared = true): \App\Services\Flashcard\FsrsClientService
    {
        return $getShared ? static::getSharedInstance('fsrs') : new \App\Services\Flashcard\FsrsClientService();
    }

    public static function flashcardValidation(bool $getShared = true): \App\Services\Flashcard\FlashcardValidationService
    {
        return $getShared ? static::getSharedInstance('flashcardValidation') : new \App\Services\Flashcard\FlashcardValidationService();
    }

    public static function aiUsage(bool $getShared = true): \App\Services\Flashcard\AiUsageService
    {
        return $getShared ? static::getSharedInstance('aiUsage') : new \App\Services\Flashcard\AiUsageService();
    }
}
