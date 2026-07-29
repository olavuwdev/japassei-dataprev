<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudyFlashcardSessionsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'uuid' => ['type' => 'CHAR', 'constraint' => 36],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'subject_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'topic_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
            'planned_total' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'reviewed_total' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'new_total' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'again_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'hard_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'good_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'easy_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'started_at' => ['type' => 'DATETIME', 'null' => true],
            'finished_at' => ['type' => 'DATETIME', 'null' => true],
            'duration_seconds' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('uuid');
        $this->forge->addKey(['user_id', 'status']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('study_flashcard_sessions', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_flashcard_sessions', true);
    }
}
