<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class StudyTaskChecklistModel extends Model
{
    protected $table          = 'study_task_checklists';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'task_id',
        'title',
        'estimated_minutes',
        'position',
        'is_required',
        'is_completed',
        'completed_at',
    ];

    protected $validationRules = [
        'title' => 'required|min_length[2]|max_length[255]',
    ];

    protected $validationMessages = [
        'title' => [
            'required'   => 'O título é obrigatório.',
            'min_length' => 'O título deve ter no mínimo 2 caracteres.',
            'max_length' => 'O título deve ter no máximo 255 caracteres.',
        ],
    ];

    protected $skipValidation = false;
}
