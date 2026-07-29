<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class FlashcardSourceModel extends Model
{
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE       = 'done';
    public const STATUS_WARNING    = 'warning';
    public const STATUS_ERROR      = 'error';
    public const STATUS_CANCELLED  = 'cancelled';

    protected $table          = 'study_flashcard_sources';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'user_id',
        'subject_id',
        'topic_id',
        'source_type',
        'provider',
        'title',
        'url',
        'raw_content',
        'clean_content',
        'content_hash',
        'status',
        'error_message',
        'cards_count',
        'processed_at',
    ];

    protected $skipValidation = true;
}
