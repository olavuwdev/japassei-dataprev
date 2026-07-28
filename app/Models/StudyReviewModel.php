<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class StudyReviewModel extends Model
{
    protected $table          = 'study_reviews';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'user_id',
        'origin_task_id',
        'subject_id',
        'topic_id',
        'review_number',
        'interval_days',
        'due_date',
        'status',
        'difficulty',
        'questions_total',
        'questions_correct',
        'notes',
        'completed_at',
    ];

    protected $validationRules = [
        'questions_total'   => 'permit_empty|is_natural',
        'questions_correct' => 'permit_empty|is_natural',
    ];

    protected $validationMessages = [
        'questions_total' => [
            'is_natural' => 'A quantidade de questões deve ser um número inteiro maior ou igual a zero.',
        ],
        'questions_correct' => [
            'is_natural' => 'A quantidade de acertos deve ser um número inteiro maior ou igual a zero.',
        ],
    ];

    protected $skipValidation = false;
}
