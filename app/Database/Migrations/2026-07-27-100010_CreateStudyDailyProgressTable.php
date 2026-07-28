<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudyDailyProgressTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'progress_date' => ['type' => 'DATE'],
            'planned_minutes' => ['type' => 'INT', 'unsigned' => true, 'default' => 60],
            'studied_minutes' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'tasks_planned' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'tasks_completed' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'questions_total' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'questions_correct' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'reviews_completed' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'goal_met' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'xp_earned' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('progress_date');
        $this->forge->addUniqueKey(['user_id', 'progress_date']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('study_daily_progress', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_daily_progress', true);
    }
}
