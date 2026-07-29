<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Log imutável de cada avaliação. `uuid` garante idempotência: a mesma
 * requisição enviada duas vezes registra uma única revisão.
 */
class CreateStudyFlashcardReviewsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'uuid' => ['type' => 'CHAR', 'constraint' => 36],
            'state_id' => ['type' => 'INT', 'unsigned' => true],
            'flashcard_id' => ['type' => 'INT', 'unsigned' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'session_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'rating' => ['type' => 'TINYINT', 'unsigned' => true],
            'state_before' => ['type' => 'TINYINT', 'unsigned' => true],
            'state_after' => ['type' => 'TINYINT', 'unsigned' => true],
            'due_before' => ['type' => 'DATETIME', 'null' => true],
            'due_after' => ['type' => 'DATETIME'],
            'stability_before' => ['type' => 'DECIMAL', 'constraint' => '16,8', 'default' => 0],
            'stability_after' => ['type' => 'DECIMAL', 'constraint' => '16,8', 'default' => 0],
            'difficulty_before' => ['type' => 'DECIMAL', 'constraint' => '16,8', 'default' => 0],
            'difficulty_after' => ['type' => 'DECIMAL', 'constraint' => '16,8', 'default' => 0],
            'elapsed_days' => ['type' => 'INT', 'default' => 0],
            'scheduled_days' => ['type' => 'INT', 'default' => 0],
            'question_ms' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'answer_ms' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'reviewed_at' => ['type' => 'DATETIME'],
            'undone' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'fsrs_log' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('uuid');
        $this->forge->addKey(['user_id', 'reviewed_at']);
        $this->forge->addKey(['flashcard_id', 'undone']);
        $this->forge->addKey('session_id');
        $this->forge->addForeignKey('state_id', 'study_flashcard_states', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('flashcard_id', 'study_flashcards', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('session_id', 'study_flashcard_sessions', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('study_flashcard_reviews', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_flashcard_reviews', true);
    }
}
