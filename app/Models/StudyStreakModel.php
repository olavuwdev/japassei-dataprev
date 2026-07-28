<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class StudyStreakModel extends Model
{
    protected $table          = 'study_streaks';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'user_id',
        'current_streak',
        'best_streak',
        'total_qualified_days',
        'last_qualified_date',
        'record_date',
    ];

    protected $validationRules    = [];
    protected $validationMessages = [];

    protected $skipValidation = false;
}
