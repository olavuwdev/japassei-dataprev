<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class StudyQuestionAttemptModel extends Model
{
    protected $table          = 'study_question_attempts';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'user_id',
        'subject_id',
        'topic_id',
        'resource_id',
        'attempt_date',
        'source',
        'questions_total',
        'questions_correct',
        'questions_wrong',
        'questions_blank',
        'duration_minutes',
        'score_percentage',
        'error_notes',
    ];

    protected $validationRules = [
        'questions_total'   => 'required|is_natural_no_zero',
        'questions_correct' => 'required|is_natural',
        'questions_wrong'   => 'required|is_natural',
        'questions_blank'   => 'permit_empty|is_natural',
        'attempt_date'      => 'required|valid_date',
    ];

    protected $validationMessages = [
        'questions_total' => [
            'required'           => 'A quantidade total de questões é obrigatória.',
            'is_natural_no_zero' => 'A quantidade total de questões deve ser um número inteiro maior que zero.',
        ],
        'questions_correct' => [
            'required'   => 'A quantidade de acertos é obrigatória.',
            'is_natural' => 'A quantidade de acertos deve ser um número inteiro maior ou igual a zero.',
        ],
        'questions_wrong' => [
            'required'   => 'A quantidade de erros é obrigatória.',
            'is_natural' => 'A quantidade de erros deve ser um número inteiro maior ou igual a zero.',
        ],
        'questions_blank' => [
            'is_natural' => 'A quantidade de questões em branco deve ser um número inteiro maior ou igual a zero.',
        ],
        'attempt_date' => [
            'required'   => 'A data é obrigatória.',
            'valid_date' => 'Informe uma data válida.',
        ],
    ];

    protected $skipValidation = false;
}
