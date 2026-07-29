<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class FlashcardAiJobModel extends Model
{
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE       = 'done';
    public const STATUS_WARNING    = 'warning';
    public const STATUS_ERROR      = 'error';
    public const STATUS_CANCELLED  = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_PENDING    => 'Pendente',
        self::STATUS_PROCESSING => 'Processando',
        self::STATUS_DONE       => 'Concluído',
        self::STATUS_WARNING    => 'Concluído com alertas',
        self::STATUS_ERROR      => 'Erro',
        self::STATUS_CANCELLED  => 'Cancelado',
    ];

    protected $table         = 'study_flashcard_ai_jobs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'uuid',
        'user_id',
        'source_id',
        'flashcard_id',
        'job_type',
        'model',
        'status',
        'stage',
        'attempts',
        'options',
        'prompt_version',
        'schema_version',
        'input_tokens',
        'output_tokens',
        'estimated_cost',
        'response_id',
        'warnings',
        'error_message',
        'started_at',
        'finished_at',
        'duration_ms',
    ];

    protected $skipValidation = true;
}
