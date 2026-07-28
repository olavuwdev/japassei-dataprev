<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudyBadgesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 50],
            'title' => ['type' => 'VARCHAR', 'constraint' => 120],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'icon' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'xp_reward' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'sort_order' => ['type' => 'INT', 'default' => 0],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('code');
        $this->forge->addKey('active');
        $this->forge->createTable('study_badges', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_badges', true);
    }
}
