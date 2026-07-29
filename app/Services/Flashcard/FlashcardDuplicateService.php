<?php

declare(strict_types=1);

namespace App\Services\Flashcard;

use App\Models\FlashcardModel;

/**
 * Assinatura normalizada de conteúdo e detecção de duplicidades exatas.
 */
class FlashcardDuplicateService
{
    public function __construct(private ?FlashcardValidationService $validator = null)
    {
        $this->validator ??= new FlashcardValidationService();
    }

    /**
     * Assinatura de um cartão: usuário + disciplina + assunto + pergunta + resposta,
     * tudo normalizado (sem HTML, sem acento, sem caixa, sem espaço duplicado).
     */
    public function signature(
        int $userId,
        ?int $subjectId,
        ?int $topicId,
        string $front,
        string $back,
        ?int $clozeIndex = null
    ): string {
        $parts = [
            (string) $userId,
            (string) ($subjectId ?? 0),
            (string) ($topicId ?? 0),
            $this->normalize($front),
            $this->normalize($back),
            // Lacunas irmãs compartilham o mesmo texto: sem o índice, todas
            // depois da primeira seriam descartadas como duplicatas.
            $clozeIndex === null ? '' : 'c' . $clozeIndex,
        ];

        return hash('sha256', implode('|', $parts));
    }

    /**
     * Normalização agressiva usada apenas para comparação.
     */
    public function normalize(?string $value): string
    {
        $text = $this->validator->plainText($value);
        $text = mb_strtolower($text, 'UTF-8');

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if (is_string($transliterated) && $transliterated !== '') {
            $text = $transliterated;
        }

        $text = preg_replace('/[^a-z0-9\s]/u', ' ', $text) ?? '';
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return trim($text);
    }

    /**
     * Cartão já existente com a mesma assinatura, ou null.
     */
    public function findExisting(int $userId, string $signature): ?array
    {
        return (new FlashcardModel())
            ->where('user_id', $userId)
            ->where('content_signature', $signature)
            ->first();
    }

    /**
     * Marca duplicidades dentro de um mesmo lote.
     *
     * @param list<array{front:string, back:string}> $cards
     *
     * @return list<int> índices considerados duplicados dentro do lote
     */
    public function duplicatesWithinBatch(array $cards): array
    {
        $seen       = [];
        $duplicates = [];

        foreach ($cards as $index => $card) {
            $key = $this->normalize($card['front'] ?? '') . '::' . $this->normalize($card['back'] ?? '');

            if (isset($seen[$key])) {
                $duplicates[] = $index;
                continue;
            }

            $seen[$key] = $index;
        }

        return $duplicates;
    }
}
