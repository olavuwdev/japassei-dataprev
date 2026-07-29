<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class FlashcardReviewLogModel extends Model
{
    public const RATING_AGAIN = 1;
    public const RATING_HARD  = 2;
    public const RATING_GOOD  = 3;
    public const RATING_EASY  = 4;

    public const RATING_LABELS = [
        self::RATING_AGAIN => 'Não lembrei',
        self::RATING_HARD  => 'Difícil',
        self::RATING_GOOD  => 'Bom',
        self::RATING_EASY  => 'Fácil',
    ];

    protected $table         = 'study_flashcard_reviews';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'uuid',
        'state_id',
        'flashcard_id',
        'user_id',
        'session_id',
        'rating',
        'state_before',
        'state_after',
        'due_before',
        'due_after',
        'stability_before',
        'stability_after',
        'difficulty_before',
        'difficulty_after',
        'elapsed_days',
        'scheduled_days',
        'question_ms',
        'answer_ms',
        'reviewed_at',
        'undone',
        'fsrs_log',
    ];

    protected $skipValidation = true;
}
