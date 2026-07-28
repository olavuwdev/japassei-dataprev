<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudySubjectsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'exam_id' => ['type' => 'INT', 'unsigned' => true],
            'parent_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'slug' => ['type' => 'VARCHAR', 'constraint' => 150],
            'category' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'general'],
            'description' => ['type' => 'TEXT', 'null' => true],
            'priority' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 3],
            'weight' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'color' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'icon' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'sort_order' => ['type' => 'INT', 'default' => 0],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('exam_id');
        $this->forge->addKey('parent_id');
        $this->forge->addKey('category');
        $this->forge->addKey('active');
        $this->forge->addUniqueKey(['exam_id', 'slug']);
        $this->forge->addForeignKey('exam_id', 'study_exams', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('study_subjects', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_subjects', true);
    }
}
