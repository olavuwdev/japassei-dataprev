<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Estado FSRS individual de cada cartão. Todas as datas em UTC.
 * state: 0 Novo · 1 Aprendendo · 2 Revisão · 3 Reaprendendo
 */
class CreateStudyFlashcardStatesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'flashcard_id' => ['type' => 'INT', 'unsigned' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'due' => ['type' => 'DATETIME'],
            'stability' => ['type' => 'DECIMAL', 'constraint' => '16,8', 'default' => 0],
            'difficulty' => ['type' => 'DECIMAL', 'constraint' => '16,8', 'default' => 0],
            'elapsed_days' => ['type' => 'INT', 'default' => 0],
            'scheduled_days' => ['type' => 'INT', 'default' => 0],
            'reps' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'lapses' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'state' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
            'learning_step' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'last_review' => ['type' => 'DATETIME', 'null' => true],
            'in_queue' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'version' => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['flashcard_id', 'user_id']);
        $this->forge->addKey(['user_id', 'due']);
        $this->forge->addKey(['user_id', 'state', 'due']);
        $this->forge->addForeignKey('flashcard_id', 'study_flashcards', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('study_flashcard_states', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_flashcard_states', true);
    }
}
