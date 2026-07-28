<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudyStreaksTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'current_streak' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'best_streak' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'total_qualified_days' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'last_qualified_date' => ['type' => 'DATE', 'null' => true],
            'record_date' => ['type' => 'DATE', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('study_streaks', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_streaks', true);
    }
}
