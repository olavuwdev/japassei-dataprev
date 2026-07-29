<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudyFlashcardSettingsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'request_retention' => ['type' => 'DECIMAL', 'constraint' => '5,4', 'default' => 0.9],
            'maximum_interval' => ['type' => 'INT', 'unsigned' => true, 'default' => 36500],
            'new_per_day' => ['type' => 'INT', 'unsigned' => true, 'default' => 20],
            'reviews_per_day' => ['type' => 'INT', 'unsigned' => true, 'default' => 9999],
            'learning_steps' => ['type' => 'VARCHAR', 'constraint' => 191, 'default' => '["1m","10m"]'],
            'relearning_steps' => ['type' => 'VARCHAR', 'constraint' => 191, 'default' => '["10m"]'],
            'enable_fuzz' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'enable_short_term' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'show_intervals' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'show_timer' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'keyboard_shortcuts' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'shuffle_cards' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'bury_siblings' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'flip_animation' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'backlog_threshold' => ['type' => 'INT', 'unsigned' => true, 'default' => 100],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('study_flashcard_settings', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_flashcard_settings', true);
    }
}
