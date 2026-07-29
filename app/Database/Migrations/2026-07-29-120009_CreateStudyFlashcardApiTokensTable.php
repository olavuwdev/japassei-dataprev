<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tokens de integração externa. Somente o hash é persistido; o valor
 * completo é exibido uma única vez, no momento da criação.
 */
class CreateStudyFlashcardApiTokensTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'uuid' => ['type' => 'CHAR', 'constraint' => 36],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'prefix' => ['type' => 'VARCHAR', 'constraint' => 24],
            'last_four' => ['type' => 'VARCHAR', 'constraint' => 8, 'null' => true],
            'token_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'scopes' => ['type' => 'TEXT', 'null' => true],
            'requires_approval' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'request_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'last_used_at' => ['type' => 'DATETIME', 'null' => true],
            'expires_at' => ['type' => 'DATETIME', 'null' => true],
            'revoked_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('uuid');
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addKey(['user_id', 'active']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('study_flashcard_api_tokens', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_flashcard_api_tokens', true);
    }
}
