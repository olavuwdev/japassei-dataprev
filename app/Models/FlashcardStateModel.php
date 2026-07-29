<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class FlashcardStateModel extends Model
{
    public const STATE_NEW        = 0;
    public const STATE_LEARNING   = 1;
    public const STATE_REVIEW     = 2;
    public const STATE_RELEARNING = 3;

    public const STATE_LABELS = [
        self::STATE_NEW        => 'Novo',
        self::STATE_LEARNING   => 'Aprendendo',
        self::STATE_REVIEW     => 'Revisão',
        self::STATE_RELEARNING => 'Reaprendendo',
    ];

    protected $table         = 'study_flashcard_states';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'flashcard_id',
        'user_id',
        'due',
        'stability',
        'difficulty',
        'elapsed_days',
        'scheduled_days',
        'reps',
        'lapses',
        'state',
        'learning_step',
        'last_review',
        'in_queue',
        'version',
    ];

    protected $skipValidation = true;
}
