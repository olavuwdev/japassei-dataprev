<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudyUserSettingsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'daily_goal_minutes' => ['type' => 'INT', 'unsigned' => true, 'default' => 60],
            'timezone' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'America/Fortaleza'],
            'study_weekdays' => ['type' => 'TEXT', 'null' => true],
            'review_intervals' => ['type' => 'TEXT', 'null' => true],
            'auto_complete_tasks' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'notifications_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('study_user_settings', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_user_settings', true);
    }
}
