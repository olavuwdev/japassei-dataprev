<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Cartões sugeridos pela IA antes da aprovação humana. Nada entra na fila
 * de estudos sem passar por aqui.
 */
class CreateStudyFlashcardAiSuggestionsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'job_id' => ['type' => 'INT', 'unsigned' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'subject_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'topic_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'card_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'basic'],
            'question' => ['type' => 'LONGTEXT'],
            'answer' => ['type' => 'LONGTEXT', 'null' => true],
            'explanation' => ['type' => 'LONGTEXT', 'null' => true],
            'source_excerpt' => ['type' => 'LONGTEXT', 'null' => true],
            'tags' => ['type' => 'TEXT', 'null' => true],
            'difficulty' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'confidence' => ['type' => 'DECIMAL', 'constraint' => '5,4', 'null' => true],
            'reverse_recommended' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'duplicate_of' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'rejection_reason' => ['type' => 'TEXT', 'null' => true],
            'approved_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['job_id', 'status']);
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('job_id', 'study_flashcard_ai_jobs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('study_flashcard_ai_suggestions', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_flashcard_ai_suggestions', true);
    }
}
