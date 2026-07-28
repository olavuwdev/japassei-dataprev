<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class StudyTaskStatusHistoryModel extends Model
{
    protected $table          = 'study_task_status_history';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = false;
    protected $useSoftDeletes = false;

    // A tabela não possui updated_at; created_at é preenchido manualmente.
    protected $allowedFields = [
        'task_id',
        'user_id',
        'from_column_id',
        'to_column_id',
        'from_status',
        'to_status',
        'created_at',
    ];

    protected $validationRules    = [];
    protected $validationMessages = [];

    protected $skipValidation = false;
}
