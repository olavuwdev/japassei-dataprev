<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class StudyExamResourceModel extends Model
{
    protected $table          = 'study_exam_resources';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'exam_id',
        'year',
        'organizer',
        'title',
        'description',
        'resource_type',
        'url',
        'is_official',
        'is_active',
        'sort_order',
    ];

    protected $validationRules = [
        'title'     => 'required|min_length[3]',
        'url'       => 'required|valid_url_strict',
        'year'      => 'required|integer',
        'organizer' => 'required',
    ];

    protected $validationMessages = [
        'title' => [
            'required'   => 'O título é obrigatório.',
            'min_length' => 'O título deve ter no mínimo 3 caracteres.',
        ],
        'url' => [
            'required'         => 'A URL é obrigatória.',
            'valid_url_strict' => 'Informe uma URL válida.',
        ],
        'year' => [
            'required' => 'O ano é obrigatório.',
            'integer'  => 'O ano deve ser um número inteiro.',
        ],
        'organizer' => [
            'required' => 'A organizadora é obrigatória.',
        ],
    ];

    protected $skipValidation = false;
}
