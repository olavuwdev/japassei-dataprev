<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudyFlashcardAiJobsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'uuid' => ['type' => 'CHAR', 'constraint' => 36],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'source_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'flashcard_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'job_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'generate'],
            'model' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'stage' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'attempts' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'options' => ['type' => 'TEXT', 'null' => true],
            'prompt_version' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'schema_version' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'input_tokens' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'output_tokens' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'estimated_cost' => ['type' => 'DECIMAL', 'constraint' => '12,6', 'null' => true],
            'response_id' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'warnings' => ['type' => 'TEXT', 'null' => true],
            'error_message' => ['type' => 'TEXT', 'null' => true],
            'started_at' => ['type' => 'DATETIME', 'null' => true],
            'finished_at' => ['type' => 'DATETIME', 'null' => true],
            'duration_ms' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('uuid');
        $this->forge->addKey(['user_id', 'created_at']);
        $this->forge->addKey('status');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('source_id', 'study_flashcard_sources', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('flashcard_id', 'study_flashcards', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('study_flashcard_ai_jobs', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_flashcard_ai_jobs', true);
    }
}
