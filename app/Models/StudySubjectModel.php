<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class StudySubjectModel extends Model
{
    protected $table          = 'study_subjects';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'exam_id',
        'parent_id',
        'name',
        'slug',
        'category',
        'description',
        'priority',
        'weight',
        'color',
        'icon',
        'sort_order',
        'active',
    ];

    protected $validationRules    = [];
    protected $validationMessages = [];

    protected $skipValidation = false;
}
