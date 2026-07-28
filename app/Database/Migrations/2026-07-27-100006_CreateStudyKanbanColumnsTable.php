<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudyKanbanColumnsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 30],
            'title' => ['type' => 'VARCHAR', 'constraint' => 100],
            'color' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'position' => ['type' => 'INT', 'default' => 0],
            'wip_limit' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'is_completed_column' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('code');
        $this->forge->addKey('active');
        $this->forge->createTable('study_kanban_columns', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_kanban_columns', true);
    }
}
