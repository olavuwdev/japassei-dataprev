<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class FlashcardApiTokenModel extends Model
{
    protected $table         = 'study_flashcard_api_tokens';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'uuid',
        'user_id',
        'name',
        'prefix',
        'last_four',
        'token_hash',
        'scopes',
        'requires_approval',
        'request_count',
        'active',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected $skipValidation = true;
}
