<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Anotação: a informação central. Uma anotação gera um ou mais cartões
 * (básico, reverso, cloze numerado, resposta digitada).
 */
class CreateStudyFlashcardNotesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'source_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'subject_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'topic_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'note_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'basic'],
            'base_content' => ['type' => 'LONGTEXT', 'null' => true],
            'tags' => ['type' => 'TEXT', 'null' => true],
            'ai_generated' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'origin' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'manual'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('user_id');
        $this->forge->addKey('source_id');
        $this->forge->addKey('subject_id');
        $this->forge->addKey('topic_id');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('source_id', 'study_flashcard_sources', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('subject_id', 'study_subjects', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('topic_id', 'study_topics', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('study_flashcard_notes', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_flashcard_notes', true);
    }
}
