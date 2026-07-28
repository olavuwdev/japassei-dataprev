<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class StudyUserSettingModel extends Model
{
    protected $table          = 'study_user_settings';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'user_id',
        'daily_goal_minutes',
        'timezone',
        'study_weekdays',
        'review_intervals',
        'auto_complete_tasks',
        'notifications_enabled',
    ];

    protected $validationRules    = [];
    protected $validationMessages = [];

    protected $skipValidation = false;
}
