<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class StudyPlanModel extends Model
{
    protected $table          = 'study_plans';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'user_id',
        'exam_id',
        'name',
        'start_date',
        'end_date',
        'daily_minutes',
        'weekdays',
        'review_intervals',
        'active',
    ];

    protected $validationRules    = [];
    protected $validationMessages = [];

    protected $skipValidation = false;
}
