<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class StudyUserBadgeModel extends Model
{
    protected $table          = 'study_user_badges';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = false;
    protected $useSoftDeletes = false;

    // A tabela não possui updated_at; created_at é preenchido manualmente.
    protected $allowedFields = [
        'user_id',
        'badge_id',
        'earned_at',
        'created_at',
    ];

    protected $validationRules    = [];
    protected $validationMessages = [];

    protected $skipValidation = false;
}
