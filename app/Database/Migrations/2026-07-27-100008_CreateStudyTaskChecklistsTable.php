<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudyTaskChecklistsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'task_id' => ['type' => 'INT', 'unsigned' => true],
            'title' => ['type' => 'VARCHAR', 'constraint' => 200],
            'estimated_minutes' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'position' => ['type' => 'INT', 'default' => 0],
            'is_required' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'is_completed' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'completed_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('task_id');
        $this->forge->addForeignKey('task_id', 'study_tasks', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('study_task_checklists', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_task_checklists', true);
    }
}
