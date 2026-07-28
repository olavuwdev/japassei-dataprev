<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudyQuestionAttemptsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'subject_id' => ['type' => 'INT', 'unsigned' => true],
            'topic_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'resource_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'attempt_date' => ['type' => 'DATE'],
            'source' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'questions_total' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'questions_correct' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'questions_wrong' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'questions_blank' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'duration_minutes' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'score_percentage' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'error_notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('user_id');
        $this->forge->addKey('subject_id');
        $this->forge->addKey('attempt_date');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('subject_id', 'study_subjects', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('topic_id', 'study_topics', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('resource_id', 'study_exam_resources', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('study_question_attempts', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_question_attempts', true);
    }
}
