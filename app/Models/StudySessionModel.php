<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class StudySessionModel extends Model
{
    protected $table          = 'study_sessions';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'user_id',
        'task_id',
        'subject_id',
        'topic_id',
        'session_type',
        'started_at',
        'ended_at',
        'duration_seconds',
        'planned_minutes',
        'status',
        'notes',
        'last_resumed_at',
    ];

    protected $validationRules    = [];
    protected $validationMessages = [];

    protected $skipValidation = false;
}
