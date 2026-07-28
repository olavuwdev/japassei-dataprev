<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudyPlansTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'exam_id' => ['type' => 'INT', 'unsigned' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'start_date' => ['type' => 'DATE'],
            'end_date' => ['type' => 'DATE', 'null' => true],
            'daily_minutes' => ['type' => 'INT', 'unsigned' => true, 'default' => 60],
            'weekdays' => ['type' => 'TEXT', 'null' => true],
            'review_intervals' => ['type' => 'TEXT', 'null' => true],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('user_id');
        $this->forge->addKey('exam_id');
        $this->forge->addKey('active');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('exam_id', 'study_exams', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('study_plans', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_plans', true);
    }
}
