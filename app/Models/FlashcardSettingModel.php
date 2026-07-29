<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class FlashcardSettingModel extends Model
{
    protected $table         = 'study_flashcard_settings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'user_id',
        'request_retention',
        'maximum_interval',
        'new_per_day',
        'reviews_per_day',
        'learning_steps',
        'relearning_steps',
        'enable_fuzz',
        'enable_short_term',
        'show_intervals',
        'show_timer',
        'keyboard_shortcuts',
        'shuffle_cards',
        'bury_siblings',
        'flip_animation',
        'backlog_threshold',
    ];

    protected $skipValidation = true;

    /**
     * Configurações do usuário, criando o registro padrão na primeira leitura.
     */
    public function forUser(int $userId): array
    {
        $settings = $this->where('user_id', $userId)->first();

        if ($settings === null) {
            $this->insert(['user_id' => $userId]);
            $settings = $this->where('user_id', $userId)->first() ?? [];
        }

        return $settings;
    }
}
