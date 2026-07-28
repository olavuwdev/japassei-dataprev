<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class StudyResourceAttemptModel extends Model
{
    protected $table          = 'study_resource_attempts';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'user_id',
        'resource_id',
        'attempted_at',
        'questions_total',
        'questions_correct',
        'questions_wrong',
        'questions_blank',
        'duration_minutes',
        'score_percentage',
        'notes',
    ];

    protected $validationRules    = [];
    protected $validationMessages = [];

    protected $skipValidation = false;
}
