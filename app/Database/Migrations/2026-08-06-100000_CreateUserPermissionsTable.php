<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Services\Auth\Permissions;
use CodeIgniter\Database\Migration;

/**
 * Permissões por módulo, uma linha por usuário/permissão.
 *
 * Os usuários que já existiam recebem o catálogo completo: a tabela nasce vazia
 * e, sem esse preenchimento, todo mundo — inclusive quem administra — ficaria
 * trancado do lado de fora no primeiro acesso após a migração.
 */
class CreateUserPermissionsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'permission' => ['type' => 'VARCHAR', 'constraint' => 60],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['user_id', 'permission']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('user_permissions', false, ['ENGINE' => 'InnoDB']);

        $users = $this->db->table('users')->select('id')->get()->getResultArray();

        if ($users === []) {
            return;
        }

        $now  = date('Y-m-d H:i:s');
        $rows = [];

        foreach ($users as $user) {
            foreach (Permissions::codes() as $code) {
                $rows[] = [
                    'user_id'    => (int) $user['id'],
                    'permission' => $code,
                    'created_at' => $now,
                ];
            }
        }

        $this->db->table('user_permissions')->insertBatch($rows);
    }

    public function down(): void
    {
        $this->forge->dropTable('user_permissions', true);
    }
}
