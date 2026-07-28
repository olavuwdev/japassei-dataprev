<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class StudyTopicModel extends Model
{
    protected $table          = 'study_topics';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'subject_id',
        'parent_id',
        'name',
        'slug',
        'description',
        'estimated_minutes',
        'difficulty',
        'sort_order',
        'active',
    ];

    protected $validationRules    = [];
    protected $validationMessages = [];

    protected $skipValidation = false;
}
