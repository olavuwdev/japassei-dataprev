<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class FlashcardImportModel extends Model
{
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE       = 'done';
    public const STATUS_PARTIAL    = 'partial';
    public const STATUS_ERROR      = 'error';

    protected $table         = 'study_flashcard_imports';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'uuid',
        'external_id',
        'user_id',
        'token_id',
        'provider',
        'idempotency_key',
        'status',
        'received_count',
        'created_count',
        'duplicate_count',
        'rejected_count',
        'pending_count',
        'response_payload',
        'ip_address',
        'user_agent',
        'error_message',
        'processed_at',
    ];

    protected $skipValidation = true;
}
