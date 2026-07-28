<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudyTopicsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'subject_id' => ['type' => 'INT', 'unsigned' => true],
            'parent_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'slug' => ['type' => 'VARCHAR', 'constraint' => 150],
            'description' => ['type' => 'TEXT', 'null' => true],
            'estimated_minutes' => ['type' => 'INT', 'unsigned' => true, 'default' => 60],
            'difficulty' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 2],
            'sort_order' => ['type' => 'INT', 'default' => 0],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('subject_id');
        $this->forge->addKey('parent_id');
        $this->forge->addKey('active');
        $this->forge->addUniqueKey(['subject_id', 'slug']);
        $this->forge->addForeignKey('subject_id', 'study_subjects', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('study_topics', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_topics', true);
    }
}
