<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class StudyBadgeModel extends Model
{
    protected $table          = 'study_badges';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'code',
        'title',
        'description',
        'icon',
        'xp_reward',
        'sort_order',
        'active',
    ];

    protected $validationRules    = [];
    protected $validationMessages = [];

    protected $skipValidation = false;
}
