<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Log de cada importação recebida pela API externa. O token recebido
 * nunca é armazenado — apenas a referência ao registro de token.
 */
class CreateStudyFlashcardImportsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'uuid' => ['type' => 'CHAR', 'constraint' => 36],
            'external_id' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'token_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'provider' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'idempotency_key' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'processing'],
            'received_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'created_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'duplicate_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'rejected_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'pending_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'response_payload' => ['type' => 'LONGTEXT', 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'error_message' => ['type' => 'LONGTEXT', 'null' => true],
            'processed_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('uuid');
        $this->forge->addUniqueKey(['user_id', 'idempotency_key']);
        $this->forge->addKey(['user_id', 'created_at']);
        $this->forge->addKey('external_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('token_id', 'study_flashcard_api_tokens', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('study_flashcard_imports', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_flashcard_imports', true);
    }
}
