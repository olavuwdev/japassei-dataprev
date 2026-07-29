<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class FlashcardAuditLogModel extends Model
{
    protected $table         = 'study_flashcard_audit_logs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'user_id',
        'event',
        'entity_type',
        'entity_id',
        'context',
        'ip_address',
    ];

    protected $skipValidation = true;
}
