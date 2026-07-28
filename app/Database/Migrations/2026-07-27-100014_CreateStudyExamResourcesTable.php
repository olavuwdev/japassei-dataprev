<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudyExamResourcesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'exam_id' => ['type' => 'INT', 'unsigned' => true],
            'year' => ['type' => 'INT', 'unsigned' => true],
            'organizer' => ['type' => 'VARCHAR', 'constraint' => 100],
            'title' => ['type' => 'VARCHAR', 'constraint' => 200],
            'description' => ['type' => 'TEXT', 'null' => true],
            'resource_type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'url' => ['type' => 'VARCHAR', 'constraint' => 500],
            'is_official' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'sort_order' => ['type' => 'INT', 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('exam_id');
        $this->forge->addKey('resource_type');
        $this->forge->addKey('is_active');
        $this->forge->addForeignKey('exam_id', 'study_exams', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('study_exam_resources', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_exam_resources', true);
    }
}
