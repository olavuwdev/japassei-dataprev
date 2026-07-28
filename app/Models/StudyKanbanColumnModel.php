<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class StudyKanbanColumnModel extends Model
{
    protected $table          = 'study_kanban_columns';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'code',
        'title',
        'color',
        'position',
        'wip_limit',
        'is_completed_column',
        'active',
    ];

    protected $validationRules    = [];
    protected $validationMessages = [];

    protected $skipValidation = false;
}
