<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class StudyDailyProgressModel extends Model
{
    protected $table          = 'study_daily_progress';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'user_id',
        'progress_date',
        'planned_minutes',
        'studied_minutes',
        'tasks_planned',
        'tasks_completed',
        'questions_total',
        'questions_correct',
        'reviews_completed',
        'goal_met',
        'xp_earned',
    ];

    protected $validationRules    = [];
    protected $validationMessages = [];

    protected $skipValidation = false;
}
