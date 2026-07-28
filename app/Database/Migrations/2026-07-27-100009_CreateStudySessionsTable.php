<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudySessionsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'task_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'subject_id' => ['type' => 'INT', 'unsigned' => true],
            'topic_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'session_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'study'],
            'started_at' => ['type' => 'DATETIME'],
            'ended_at' => ['type' => 'DATETIME', 'null' => true],
            'last_resumed_at' => ['type' => 'DATETIME', 'null' => true],
            'duration_seconds' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'planned_minutes' => ['type' => 'INT', 'unsigned' => true, 'default' => 60],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'running'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('user_id');
        $this->forge->addKey('task_id');
        $this->forge->addKey('subject_id');
        $this->forge->addKey('status');
        $this->forge->addKey(['user_id', 'status']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('task_id', 'study_tasks', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('subject_id', 'study_subjects', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('topic_id', 'study_topics', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('study_sessions', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_sessions', true);
    }
}
