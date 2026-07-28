<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudyPlanWeeksTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'plan_id' => ['type' => 'INT', 'unsigned' => true],
            'week_number' => ['type' => 'INT', 'unsigned' => true],
            'title' => ['type' => 'VARCHAR', 'constraint' => 150],
            'objective' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'start_date' => ['type' => 'DATE'],
            'end_date' => ['type' => 'DATE'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('plan_id');
        $this->forge->addKey('status');
        $this->forge->addUniqueKey(['plan_id', 'week_number']);
        $this->forge->addForeignKey('plan_id', 'study_plans', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('study_plan_weeks', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_plan_weeks', true);
    }
}
