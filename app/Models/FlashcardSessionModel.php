<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class FlashcardSessionModel extends Model
{
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_PAUSED    = 'paused';
    public const STATUS_FINISHED  = 'finished';
    public const STATUS_ABANDONED = 'abandoned';

    protected $table         = 'study_flashcard_sessions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'uuid',
        'user_id',
        'subject_id',
        'topic_id',
        'status',
        'planned_total',
        'reviewed_total',
        'new_total',
        'again_count',
        'hard_count',
        'good_count',
        'easy_count',
        'started_at',
        'finished_at',
        'duration_seconds',
    ];

    protected $skipValidation = true;
}
