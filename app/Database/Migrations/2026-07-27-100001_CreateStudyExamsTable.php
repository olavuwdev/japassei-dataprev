<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudyExamsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'year' => ['type' => 'INT', 'unsigned' => true],
            'profile' => ['type' => 'VARCHAR', 'constraint' => 100],
            'organizer' => ['type' => 'VARCHAR', 'constraint' => 100],
            'exam_date' => ['type' => 'DATE', 'null' => true],
            'daily_minutes' => ['type' => 'INT', 'unsigned' => true, 'default' => 60],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('active');
        $this->forge->createTable('study_exams', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_exams', true);
    }
}
