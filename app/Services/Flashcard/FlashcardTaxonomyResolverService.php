<?php

declare(strict_types=1);

namespace App\Services\Flashcard;

use App\Models\StudyExamModel;
use App\Models\StudySubjectModel;
use App\Models\StudyTopicModel;
use RuntimeException;

/**
 * Localiza (ou cria) disciplina, categoria e assunto a partir dos nomes
 * enviados pela API externa.
 *
 * Mapeamento para a estrutura existente do sistema:
 *   discipline → study_subjects
 *   category   → study_topics (nível pai, opcional)
 *   subject    → study_topics (filho da categoria, quando houver)
 *
 * A comparação ignora caixa, acentuação e espaços duplicados, de modo que
 * "direito administrativo" reutiliza "Direito Administrativo".
 */
class FlashcardTaxonomyResolverService
{
    /**
     * @param array<string, mixed> $payload já normalizado pelo import service
     *
     * @return array{
     *   discipline: array{id:?int, name:?string, created:bool},
     *   category: array{id:?int, name:?string, created:bool},
     *   subject: array{id:?int, name:?string, created:bool}
     * }
     */
    public function resolve(array $payload): array
    {
        $discipline = $this->resolveDiscipline($payload['discipline'] ?? null);

        $category = $this->resolveTopic(
            $payload['category'] ?? null,
            $discipline['id'],
            null,
            'categoria'
        );

        $subject = $this->resolveTopic(
            $payload['subject'] ?? null,
            $discipline['id'],
            $category['id'],
            'assunto'
        );

        return ['discipline' => $discipline, 'category' => $category, 'subject' => $subject];
    }

    /**
     * @param array{name?:string, create_if_not_exists?:bool}|null $input
     *
     * @return array{id:?int, name:?string, created:bool}
     */
    private function resolveDiscipline(?array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));

        if ($name === '') {
            return ['id' => null, 'name' => null, 'created' => false];
        }

        $model    = new StudySubjectModel();
        $existing = $this->matchByName($model->findAll(), $name);

        if ($existing !== null) {
            return ['id' => (int) $existing['id'], 'name' => $existing['name'], 'created' => false];
        }

        if (! ($input['create_if_not_exists'] ?? false)) {
            throw new TaxonomyNotFoundException('A disciplina "' . $name . '" não existe. Envie create_if_not_exists como true para criá-la.');
        }

        $examId = $this->currentExamId();
        $slug   = $this->uniqueSlug($model, $name, $examId);

        $id = (int) $model->insert([
            'exam_id'     => $examId,
            'name'        => mb_substr($name, 0, 150),
            'slug'        => $slug,
            'category'    => 'general',
            'description' => 'Criada automaticamente por integração externa.',
            'active'      => 1,
        ], true);

        if ($id === 0) {
            throw new RuntimeException('Não foi possível criar a disciplina "' . $name . '".');
        }

        return ['id' => $id, 'name' => $name, 'created' => true];
    }

    /**
     * @param array{name?:string, create_if_not_exists?:bool}|null $input
     *
     * @return array{id:?int, name:?string, created:bool}
     */
    private function resolveTopic(?array $input, ?int $subjectId, ?int $parentId, string $label): array
    {
        $name = trim((string) ($input['name'] ?? ''));

        if ($name === '') {
            return ['id' => null, 'name' => null, 'created' => false];
        }

        if ($subjectId === null) {
            throw new TaxonomyNotFoundException('Informe a disciplina antes de definir a ' . $label . ' "' . $name . '".');
        }

        $model     = new StudyTopicModel();
        $candidates = $model->where('subject_id', $subjectId)->findAll();
        $existing  = $this->matchByName($candidates, $name);

        if ($existing !== null) {
            return ['id' => (int) $existing['id'], 'name' => $existing['name'], 'created' => false];
        }

        if (! ($input['create_if_not_exists'] ?? false)) {
            throw new TaxonomyNotFoundException('O ' . $label . ' "' . $name . '" não existe. Envie create_if_not_exists como true para criá-lo.');
        }

        $id = (int) $model->insert([
            'subject_id'  => $subjectId,
            'parent_id'   => $parentId,
            'name'        => mb_substr($name, 0, 150),
            'slug'        => $this->uniqueTopicSlug($model, $name, $subjectId),
            'description' => 'Criado automaticamente por integração externa.',
            'active'      => 1,
        ], true);

        if ($id === 0) {
            throw new RuntimeException('Não foi possível criar o ' . $label . ' "' . $name . '".');
        }

        return ['id' => $id, 'name' => $name, 'created' => true];
    }

    /**
     * Comparação tolerante: ignora caixa, acentos e espaços repetidos.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, mixed>|null
     */
    private function matchByName(array $rows, string $name): ?array
    {
        $needle = $this->normalize($name);

        foreach ($rows as $row) {
            if ($this->normalize((string) $row['name']) === $needle) {
                return $row;
            }
        }

        return null;
    }

    public function normalize(string $value): string
    {
        $text = mb_strtolower(trim($value), 'UTF-8');

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if (is_string($transliterated) && $transliterated !== '') {
            $text = $transliterated;
        }

        $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? '';

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    private function slugify(string $value): string
    {
        $slug = str_replace(' ', '-', $this->normalize($value));

        return $slug === '' ? 'item' : mb_substr($slug, 0, 140);
    }

    private function uniqueSlug(StudySubjectModel $model, string $name, int $examId): string
    {
        $base = $this->slugify($name);
        $slug = $base;
        $i    = 2;

        while ($model->where('exam_id', $examId)->where('slug', $slug)->countAllResults() > 0) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    private function uniqueTopicSlug(StudyTopicModel $model, string $name, int $subjectId): string
    {
        $base = $this->slugify($name);
        $slug = $base;
        $i    = 2;

        while ($model->where('subject_id', $subjectId)->where('slug', $slug)->countAllResults() > 0) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    /**
     * Concurso ativo, ao qual as disciplinas criadas serão vinculadas.
     */
    private function currentExamId(): int
    {
        $model = new StudyExamModel();

        $exam = $model->where('active', 1)->orderBy('id', 'DESC')->first()
            ?? $model->orderBy('id', 'ASC')->first();

        if ($exam === null) {
            throw new RuntimeException('Nenhum concurso cadastrado para vincular a disciplina.');
        }

        return (int) $exam['id'];
    }
}
