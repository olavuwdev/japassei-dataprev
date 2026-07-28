<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudyTaskStatusHistoryTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'task_id' => ['type' => 'INT', 'unsigned' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'from_column_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'to_column_id' => ['type' => 'INT', 'unsigned' => true],
            'from_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'to_status' => ['type' => 'VARCHAR', 'constraint' => 20],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('task_id');
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('task_id', 'study_tasks', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('study_task_status_history', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_task_status_history', true);
    }
}
