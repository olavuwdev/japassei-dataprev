<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class StudyPlanWeekModel extends Model
{
    protected $table          = 'study_plan_weeks';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'plan_id',
        'week_number',
        'title',
        'objective',
        'start_date',
        'end_date',
        'status',
    ];

    protected $validationRules    = [];
    protected $validationMessages = [];

    protected $skipValidation = false;
}
