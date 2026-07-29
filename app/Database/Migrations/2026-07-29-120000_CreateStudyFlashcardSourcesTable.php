<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudyFlashcardSourcesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'subject_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'topic_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'source_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'text'],
            'provider' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'title' => ['type' => 'VARCHAR', 'constraint' => 255],
            'url' => ['type' => 'TEXT', 'null' => true],
            'raw_content' => ['type' => 'LONGTEXT', 'null' => true],
            'clean_content' => ['type' => 'LONGTEXT', 'null' => true],
            'content_hash' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'error_message' => ['type' => 'TEXT', 'null' => true],
            'cards_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'processed_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('user_id');
        $this->forge->addKey('subject_id');
        $this->forge->addKey('topic_id');
        $this->forge->addKey('status');
        $this->forge->addKey('content_hash');
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('subject_id', 'study_subjects', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('topic_id', 'study_topics', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('study_flashcard_sources', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_flashcard_sources', true);
    }
}
