<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudyFlashcardsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'note_id' => ['type' => 'INT', 'unsigned' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'subject_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'topic_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'card_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'basic'],
            'front' => ['type' => 'LONGTEXT'],
            'back' => ['type' => 'LONGTEXT'],
            'explanation' => ['type' => 'LONGTEXT', 'null' => true],
            'example' => ['type' => 'LONGTEXT', 'null' => true],
            'source_excerpt' => ['type' => 'LONGTEXT', 'null' => true],
            'cloze_index' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'sort_order' => ['type' => 'INT', 'default' => 0],
            'ai_difficulty' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'ai_confidence' => ['type' => 'DECIMAL', 'constraint' => '5,4', 'null' => true],
            'content_signature' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'external_id' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            // Referência ao lote da API externa (sem FK: a tabela de importações
            // é criada depois desta).
            'import_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'suspended' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'flagged' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'edit_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
            'version' => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('note_id');
        $this->forge->addKey('user_id');
        $this->forge->addKey('subject_id');
        $this->forge->addKey('topic_id');
        $this->forge->addKey('status');
        $this->forge->addKey('suspended');
        $this->forge->addKey(['user_id', 'content_signature']);
        $this->forge->addKey(['user_id', 'external_id']);
        $this->forge->addKey('import_id');
        $this->forge->addForeignKey('note_id', 'study_flashcard_notes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('subject_id', 'study_subjects', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('topic_id', 'study_topics', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('study_flashcards', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_flashcards', true);
    }
}
