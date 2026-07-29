<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class FlashcardAiSuggestionModel extends Model
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $table         = 'study_flashcard_ai_suggestions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'job_id',
        'user_id',
        'subject_id',
        'topic_id',
        'card_type',
        'question',
        'answer',
        'explanation',
        'source_excerpt',
        'tags',
        'difficulty',
        'confidence',
        'reverse_recommended',
        'duplicate_of',
        'status',
        'rejection_reason',
        'approved_at',
    ];

    protected $skipValidation = true;
}
