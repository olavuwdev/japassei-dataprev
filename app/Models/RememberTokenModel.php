<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class RememberTokenModel extends Model
{
    protected $table         = 'auth_remember_tokens';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'user_id',
        'selector',
        'validator_hash',
        'user_agent',
        'ip_address',
        'expires_at',
        'last_used_at',
        'revoked_at',
    ];

    protected $skipValidation = true;
}
