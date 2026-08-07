<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Garante que todo registro de flashcard pertença a um usuário existente.
 *
 * As colunas `user_id` e as chaves estrangeiras já existem desde a criação das
 * tabelas, e todas as consultas do módulo já filtram por usuário — esta migração
 * apenas adota as linhas órfãs (user_id = 0 ou apontando para um usuário
 * removido) em nome do usuário 1, conforme combinado.
 */
class BackfillFlashcardOwnership extends Migration
{
    private const TABLES = [
        'study_flashcard_sources',
        'study_flashcard_notes',
        'study_flashcards',
        'study_flashcard_states',
        'study_flashcard_sessions',
        'study_flashcard_reviews',
        'study_flashcard_settings',
        'study_flashcard_ai_jobs',
        'study_flashcard_ai_suggestions',
        'study_flashcard_api_tokens',
        'study_flashcard_imports',
        'study_flashcard_audit_logs',
    ];

    public function up(): void
    {
        $owner = $this->db->table('users')->select('id')->orderBy('id', 'ASC')->get(1)->getRowArray();

        // Base ainda sem usuários: não há dono para adotar as linhas.
        if ($owner === null) {
            return;
        }

        $ownerId = (int) $owner['id'];

        foreach (self::TABLES as $table) {
            if (! $this->db->tableExists($table)) {
                continue;
            }

            $orphans = $this->db->table($table . ' t')
                ->select('t.id')
                ->join('users u', 'u.id = t.user_id', 'left')
                ->where('u.id', null)
                ->get()
                ->getResultArray();

            if ($orphans === []) {
                continue;
            }

            $ids = array_map(static fn (array $row): int => (int) $row['id'], $orphans);

            // A chave estrangeira recusa a atualização se o dono não existir;
            // como ele veio da própria tabela `users`, a operação é segura.
            $this->db->table($table)->whereIn('id', $ids)->update(['user_id' => $ownerId]);

            log_message('notice', sprintf(
                'BackfillFlashcardOwnership: %d registro(s) de %s atribuídos ao usuário %d.',
                count($ids),
                $table,
                $ownerId
            ));
        }
    }

    public function down(): void
    {
        // Sem reversão: não há como distinguir as linhas adotadas das originais.
    }
}
