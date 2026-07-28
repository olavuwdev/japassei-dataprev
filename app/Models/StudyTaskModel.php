<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class StudyTaskModel extends Model
{
    protected $table          = 'study_tasks';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'user_id',
        'plan_id',
        'plan_week_id',
        'subject_id',
        'topic_id',
        'kanban_column_id',
        'title',
        'description',
        'task_type',
        'scheduled_date',
        'estimated_minutes',
        'actual_minutes',
        'priority',
        'position',
        'status',
        'is_required',
        'completed_at',
    ];

    protected $validationRules = [
        'title'     => 'required|min_length[3]|max_length[255]',
        'task_type' => 'required|in_list[theory,questions,review,practice,mock_exam]',
        'priority'  => 'permit_empty|integer',
    ];

    protected $validationMessages = [
        'title' => [
            'required'   => 'O título é obrigatório.',
            'min_length' => 'O título deve ter no mínimo 3 caracteres.',
            'max_length' => 'O título deve ter no máximo 255 caracteres.',
        ],
        'task_type' => [
            'required' => 'O tipo da tarefa é obrigatório.',
            'in_list'  => 'O tipo da tarefa é inválido.',
        ],
        'priority' => [
            'integer' => 'A prioridade deve ser um número inteiro.',
        ],
    ];

    protected $skipValidation = false;
}
