<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class FlashcardNoteModel extends Model
{
    protected $table          = 'study_flashcard_notes';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'source_id',
        'user_id',
        'subject_id',
        'topic_id',
        'note_type',
        'base_content',
        'tags',
        'ai_generated',
        'origin',
        'status',
    ];

    protected $skipValidation = true;
}
