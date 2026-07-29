<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class FlashcardModel extends Model
{
    public const TYPE_BASIC         = 'basic';
    public const TYPE_REVERSE       = 'reverse';
    public const TYPE_CLOZE         = 'cloze';
    public const TYPE_TYPED_ANSWER  = 'typed_answer';
    public const TYPE_TRUE_FALSE    = 'true_false';

    public const TYPES = [
        self::TYPE_BASIC,
        self::TYPE_REVERSE,
        self::TYPE_CLOZE,
        self::TYPE_TYPED_ANSWER,
        self::TYPE_TRUE_FALSE,
    ];

    public const TYPE_LABELS = [
        self::TYPE_BASIC        => 'Básico',
        self::TYPE_REVERSE      => 'Reverso',
        self::TYPE_CLOZE        => 'Completar lacuna',
        self::TYPE_TYPED_ANSWER => 'Resposta digitada',
        self::TYPE_TRUE_FALSE   => 'Verdadeiro ou falso',
    ];

    public const STATUS_ACTIVE           = 'active';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_ARCHIVED         = 'archived';

    protected $table          = 'study_flashcards';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'note_id',
        'user_id',
        'subject_id',
        'topic_id',
        'card_type',
        'front',
        'back',
        'explanation',
        'example',
        'source_excerpt',
        'cloze_index',
        'sort_order',
        'ai_difficulty',
        'ai_confidence',
        'content_signature',
        'external_id',
        'import_id',
        'suspended',
        'flagged',
        'edit_count',
        'status',
        'version',
    ];

    protected $skipValidation = true;
}
