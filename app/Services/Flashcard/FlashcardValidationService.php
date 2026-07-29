<?php

declare(strict_types=1);

namespace App\Services\Flashcard;

use App\Models\FlashcardModel;

/**
 * Validação e sanitização de cartões. Usado tanto pelo cadastro manual quanto
 * pela geração por IA e pela API externa de importação.
 */
class FlashcardValidationService
{
    /** Tags HTML permitidas no conteúdo dos cartões. */
    private const ALLOWED_TAGS = '<p><br><b><strong><i><em><u><ul><ol><li><code><pre><sub><sup><mark><blockquote><table><thead><tbody><tr><th><td><h3><h4><span>';

    public const MAX_FRONT_CHARS = 2000;
    public const MAX_BACK_CHARS  = 10000;
    public const MAX_TAGS        = 20;

    /**
     * Remove scripts, estilos, eventos inline e URLs perigosas, preservando
     * uma marcação simples de formatação.
     */
    public function sanitizeHtml(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        // Remove blocos inteiros que nunca devem sobreviver.
        $clean = preg_replace('#<(script|style|iframe|object|embed|form|svg)\b[^>]*>.*?</\1>#is', '', $html) ?? '';
        $clean = preg_replace('#<(script|style|iframe|object|embed|form|svg)\b[^>]*/?>#i', '', $clean) ?? '';

        $clean = strip_tags($clean, self::ALLOWED_TAGS);

        // Atributos de evento (onclick, onerror...) e protocolos perigosos.
        $clean = preg_replace('#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $clean) ?? '';
        $clean = preg_replace('#(href|src|xlink:href)\s*=\s*("|\')?\s*(javascript|vbscript|data)\s*:#i', '$1="#', $clean) ?? '';
        $clean = preg_replace('#\sstyle\s*=\s*("[^"]*"|\'[^\']*\')#i', '', $clean) ?? '';

        return trim($clean);
    }

    /**
     * Texto puro normalizado, usado em comparações e assinaturas.
     */
    public function plainText(?string $html): string
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return trim($text);
    }

    /**
     * Valida e normaliza um cartão. Retorna
     * ['valid' => bool, 'errors' => list<array{field,code,message}>, 'card' => array].
     *
     * @param array<string, mixed> $input
     */
    public function validateCard(array $input): array
    {
        $errors = [];

        $type = (string) ($input['card_type'] ?? $input['type'] ?? FlashcardModel::TYPE_BASIC);
        if (! in_array($type, FlashcardModel::TYPES, true)) {
            $errors[] = $this->error('type', 'INVALID_TYPE', 'Tipo de cartão inválido: ' . $type . '.');
            $type     = FlashcardModel::TYPE_BASIC;
        }

        $front = $this->sanitizeHtml((string) ($input['front'] ?? $input['question'] ?? $input['text'] ?? ''));
        $back  = $this->sanitizeHtml((string) ($input['back'] ?? $input['answer'] ?? ''));

        if ($type === FlashcardModel::TYPE_CLOZE) {
            $clozeText = $front !== '' ? $front : $back;
            $cloze     = $this->parseCloze($clozeText);

            if ($cloze === []) {
                $errors[] = $this->error('text', 'INVALID_CLOZE', 'O cartão Cloze precisa de ao menos uma lacuna no formato {{c1::resposta}}.');
            } else {
                $front = $clozeText;
                $back  = $back !== '' ? $back : implode(' · ', array_column($cloze, 'answer'));
            }
        } else {
            if ($this->plainText($front) === '') {
                $errors[] = $this->error('question', 'REQUIRED_FIELD', 'A pergunta é obrigatória.');
            }

            if ($this->plainText($back) === '') {
                $errors[] = $this->error('answer', 'REQUIRED_FIELD', 'A resposta é obrigatória para cartões do tipo ' . $type . '.');
            }
        }

        if (mb_strlen($this->plainText($front)) > self::MAX_FRONT_CHARS) {
            $errors[] = $this->error('question', 'MAX_LENGTH', 'A pergunta excede ' . self::MAX_FRONT_CHARS . ' caracteres.');
        }

        if (mb_strlen($this->plainText($back)) > self::MAX_BACK_CHARS) {
            $errors[] = $this->error('answer', 'MAX_LENGTH', 'A resposta excede ' . self::MAX_BACK_CHARS . ' caracteres.');
        }

        $tags = $this->normalizeTags($input['tags'] ?? []);
        if (count($tags) > self::MAX_TAGS) {
            $errors[] = $this->error('tags', 'MAX_TAGS', 'Máximo de ' . self::MAX_TAGS . ' tags por cartão.');
            $tags     = array_slice($tags, 0, self::MAX_TAGS);
        }

        $difficulty = $input['difficulty'] ?? $input['ai_difficulty'] ?? null;
        if ($difficulty !== null && ! in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
            $difficulty = null;
        }

        return [
            'valid'  => $errors === [],
            'errors' => $errors,
            'card'   => [
                'card_type'      => $type,
                'front'          => $front,
                'back'           => $back,
                'explanation'    => $this->sanitizeHtml($input['explanation'] ?? null) ?: null,
                'example'        => $this->sanitizeHtml($input['example'] ?? null) ?: null,
                'source_excerpt' => $this->sanitizeHtml($input['source_excerpt'] ?? null) ?: null,
                'ai_difficulty'  => $difficulty,
                'tags'           => $tags,
                'external_id'    => isset($input['external_id']) ? mb_substr((string) $input['external_id'], 0, 191) : null,
            ],
        ];
    }

    /**
     * Extrai as lacunas de um texto Cloze.
     *
     * @return list<array{index:int, answer:string, hint:?string}>
     */
    public function parseCloze(string $text): array
    {
        if (preg_match_all('/\{\{c(\d+)::(.+?)(?:::(.+?))?\}\}/us', $text, $matches, PREG_SET_ORDER) === 0) {
            return [];
        }

        $found = [];

        foreach ($matches as $match) {
            $index = (int) $match[1];

            if ($index < 1 || trim($match[2]) === '') {
                continue;
            }

            $found[$index] = [
                'index'  => $index,
                'answer' => trim($match[2]),
                'hint'   => isset($match[3]) && $match[3] !== '' ? trim($match[3]) : null,
            ];
        }

        ksort($found);

        return array_values($found);
    }

    /**
     * Renderiza o texto Cloze para uma lacuna específica: a lacuna alvo vira
     * [...] e as demais são reveladas.
     */
    public function renderCloze(string $text, int $target, bool $revealTarget = false): string
    {
        return preg_replace_callback(
            '/\{\{c(\d+)::(.+?)(?:::(.+?))?\}\}/us',
            static function (array $m) use ($target, $revealTarget): string {
                $index  = (int) $m[1];
                $answer = trim($m[2]);
                $hint   = isset($m[3]) && $m[3] !== '' ? trim($m[3]) : null;

                if ($index !== $target) {
                    return $answer;
                }

                if ($revealTarget) {
                    return '<mark class="cloze-answer">' . $answer . '</mark>';
                }

                return '<span class="cloze-gap">[' . ($hint ?? '...') . ']</span>';
            },
            $text
        ) ?? $text;
    }

    /**
     * @param mixed $tags
     *
     * @return list<string>
     */
    public function normalizeTags($tags): array
    {
        if (is_string($tags)) {
            $decoded = json_decode($tags, true);
            $tags    = is_array($decoded) ? $decoded : explode(',', $tags);
        }

        if (! is_array($tags)) {
            return [];
        }

        $normalized = [];

        foreach ($tags as $tag) {
            if (! is_scalar($tag)) {
                continue;
            }

            $slug = mb_strtolower(trim((string) $tag));
            $slug = preg_replace('/\s+/u', '-', $slug) ?? '';
            $slug = preg_replace('/[^\p{L}\p{N}\-_]/u', '', $slug) ?? '';
            $slug = trim($slug, '-');

            if ($slug !== '' && ! in_array($slug, $normalized, true)) {
                $normalized[] = mb_substr($slug, 0, 50);
            }
        }

        return $normalized;
    }

    /**
     * @return array{field:string, code:string, message:string}
     */
    private function error(string $field, string $code, string $message): array
    {
        return ['field' => $field, 'code' => $code, 'message' => $message];
    }
}
