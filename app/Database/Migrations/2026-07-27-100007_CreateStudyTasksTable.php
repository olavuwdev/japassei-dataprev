<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudyTasksTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'plan_id' => ['type' => 'INT', 'unsigned' => true],
            'plan_week_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'subject_id' => ['type' => 'INT', 'unsigned' => true],
            'topic_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'kanban_column_id' => ['type' => 'INT', 'unsigned' => true],
            'title' => ['type' => 'VARCHAR', 'constraint' => 200],
            'description' => ['type' => 'TEXT', 'null' => true],
            'task_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'theory'],
            'scheduled_date' => ['type' => 'DATE', 'null' => true],
            'estimated_minutes' => ['type' => 'INT', 'unsigned' => true, 'default' => 60],
            'actual_minutes' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'priority' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 3],
            'position' => ['type' => 'INT', 'default' => 0],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'is_required' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'completed_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('user_id');
        $this->forge->addKey('scheduled_date');
        $this->forge->addKey('subject_id');
        $this->forge->addKey('topic_id');
        $this->forge->addKey('kanban_column_id');
        $this->forge->addKey('status');
        $this->forge->addKey('plan_week_id');
        $this->forge->addKey(['kanban_column_id', 'position']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('plan_id', 'study_plans', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('plan_week_id', 'study_plan_weeks', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('subject_id', 'study_subjects', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('topic_id', 'study_topics', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('kanban_column_id', 'study_kanban_columns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('study_tasks', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_tasks', true);
    }
}
