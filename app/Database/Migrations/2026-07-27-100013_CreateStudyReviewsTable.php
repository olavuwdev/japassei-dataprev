<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudyReviewsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'origin_task_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'subject_id' => ['type' => 'INT', 'unsigned' => true],
            'topic_id' => ['type' => 'INT', 'unsigned' => true],
            'review_number' => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            'interval_days' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'due_date' => ['type' => 'DATE'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'difficulty' => ['type' => 'TINYINT', 'unsigned' => true, 'null' => true],
            'questions_total' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'questions_correct' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'completed_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('user_id');
        $this->forge->addKey('due_date');
        $this->forge->addKey('status');
        $this->forge->addKey('topic_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('origin_task_id', 'study_tasks', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('subject_id', 'study_subjects', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('topic_id', 'study_topics', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('study_reviews', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_reviews', true);
    }
}
