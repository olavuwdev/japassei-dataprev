<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class StudyExamModel extends Model
{
    protected $table          = 'study_exams';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'name',
        'year',
        'profile',
        'organizer',
        'exam_date',
        'daily_minutes',
        'active',
    ];

    protected $validationRules    = [];
    protected $validationMessages = [];

    protected $skipValidation = false;
}
