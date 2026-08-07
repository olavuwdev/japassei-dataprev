<?php

declare(strict_types=1);

namespace App\Services\Auth;

/**
 * Catálogo das permissões do sistema.
 *
 * Cada código corresponde a um módulo: sem a permissão, o item some do menu e
 * a rota é bloqueada pelo PermissionFilter.
 */
final class Permissions
{
    public const ESTUDOS                = 'estudos';
    public const FLASHCARDS             = 'flashcards';
    public const FLASHCARDS_IA          = 'flashcards_ia';
    public const FLASHCARDS_INTEGRACOES = 'flashcards_integracoes';
    public const USUARIOS               = 'usuarios';

    /**
     * Rótulo e descrição de cada permissão, na ordem em que aparecem no formulário.
     *
     * @var array<string, array{label: string, description: string}>
     */
    public const CATALOG = [
        self::ESTUDOS => [
            'label'       => 'Estudos',
            'description' => 'Visão geral, cronograma, kanban, revisões, questões, provas e desempenho.',
        ],
        self::FLASHCARDS => [
            'label'       => 'Flashcards',
            'description' => 'Revisar, criar e editar cartões, além das estatísticas do baralho.',
        ],
        self::FLASHCARDS_IA => [
            'label'       => 'Gerar com IA',
            'description' => 'Gerar cartões por IA e gerenciar as fontes de conteúdo. Exige a permissão de Flashcards.',
        ],
        self::FLASHCARDS_INTEGRACOES => [
            'label'       => 'Integrações / API',
            'description' => 'Emitir tokens da API externa e revisar importações pendentes. Exige a permissão de Flashcards.',
        ],
        self::USUARIOS => [
            'label'       => 'Gerenciar usuários',
            'description' => 'Cadastrar usuários e definir as permissões de cada um.',
        ],
    ];

    /**
     * Permissões que dependem de outra para fazer sentido.
     *
     * @var array<string, string>
     */
    public const REQUIRES = [
        self::FLASHCARDS_IA          => self::FLASHCARDS,
        self::FLASHCARDS_INTEGRACOES => self::FLASHCARDS,
    ];

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::CATALOG);
    }

    public static function isValid(string $code): bool
    {
        return array_key_exists($code, self::CATALOG);
    }

    public static function label(string $code): string
    {
        return self::CATALOG[$code]['label'] ?? $code;
    }

    /**
     * Descarta códigos desconhecidos e acrescenta as dependências implícitas —
     * marcar "Gerar com IA" sem "Flashcards" deixaria o usuário sem o menu que
     * dá acesso à tela.
     *
     * @param  list<string> $codes
     * @return list<string>
     */
    public static function normalize(array $codes): array
    {
        $valid = array_values(array_filter(
            array_unique(array_map('strval', $codes)),
            static fn (string $code): bool => self::isValid($code)
        ));

        foreach ($valid as $code) {
            $required = self::REQUIRES[$code] ?? null;

            if ($required !== null && ! in_array($required, $valid, true)) {
                $valid[] = $required;
            }
        }

        // Mantém a ordem do catálogo, para exibição previsível.
        return array_values(array_filter(
            self::codes(),
            static fn (string $code): bool => in_array($code, $valid, true)
        ));
    }
}
