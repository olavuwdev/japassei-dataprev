<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class StudyStreakHistoryModel extends Model
{
    protected $table          = 'study_streak_history';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = false;
    protected $useSoftDeletes = false;

    // A tabela não possui updated_at; created_at é preenchido manualmente.
    protected $allowedFields = [
        'user_id',
        'reference_date',
        'previous_streak',
        'new_streak',
        'event_type',
        'description',
        'created_at',
    ];

    protected $validationRules    = [];
    protected $validationMessages = [];

    protected $skipValidation = false;
}
