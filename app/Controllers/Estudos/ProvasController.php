<?php

declare(strict_types=1);

namespace App\Controllers\Estudos;

use App\Controllers\BaseController;
use App\Models\StudyExamModel;
use App\Models\StudyExamResourceModel;
use App\Models\StudyResourceAttemptModel;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * Materiais e provas antigas: cards com links oficiais, tentativas do usuário
 * e manutenção dos materiais (cadastrar, editar, desativar, excluir).
 */
class ProvasController extends BaseController
{
    private const RESOURCE_TYPES = ['official_page', 'exams_answers', 'answer_key'];

    public function index(): string
    {
        $resources = (new StudyExamResourceModel())
            ->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->findAll();

        $attempts = (new StudyResourceAttemptModel())
            ->where('user_id', $this->userId())
            ->orderBy('attempted_at', 'DESC')
            ->findAll();

        $attemptsByResource = [];
        foreach ($attempts as $attempt) {
            $attemptsByResource[(int) $attempt['resource_id']][] = $attempt;
        }

        return view('estudos/provas', [
            'resources'          => $resources,
            'attemptsByResource' => $attemptsByResource,
        ]);
    }

    public function store(): ResponseInterface
    {
        $payload = (array) $this->request->getJSON(true);

        try {
            $data = $this->resourceData($payload);

            $data['exam_id']    = $this->examId();
            $data['is_active']  = 1;
            $data['sort_order'] = $this->nextSortOrder();

            $model = new StudyExamResourceModel();
            $id    = $model->insert($data);

            if (! $id) {
                throw new RuntimeException(implode(' ', $model->errors() ?: ['Não foi possível salvar o material.']));
            }

            return $this->jsonResponse(true, ['resource' => $model->find($id)], 'Material cadastrado com sucesso!');
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }

    public function update(int $id): ResponseInterface
    {
        $payload = (array) $this->request->getJSON(true);

        try {
            $resource = $this->findResource($id);
            $data     = $this->resourceData($payload);

            $model = new StudyExamResourceModel();

            if (! $model->update($resource['id'], $data)) {
                throw new RuntimeException(implode(' ', $model->errors() ?: ['Não foi possível atualizar o material.']));
            }

            return $this->jsonResponse(true, ['resource' => $model->find($id)], 'Material atualizado.');
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }

    public function deactivate(int $id): ResponseInterface
    {
        try {
            $resource = $this->findResource($id);

            (new StudyExamResourceModel())->update($resource['id'], ['is_active' => 0]);

            return $this->jsonResponse(true, [], 'Material desativado.');
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }

    public function delete(int $id): ResponseInterface
    {
        try {
            $resource = $this->findResource($id);

            (new StudyExamResourceModel())->delete($resource['id']);

            return $this->jsonResponse(true, [], 'Material excluído.');
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }

    /**
     * Registra uma tentativa do usuário em um material (prova realizada).
     */
    public function registerAttempt(int $id): ResponseInterface
    {
        $payload = (array) $this->request->getJSON(true);

        try {
            $resource = $this->findResource($id);

            $total   = max(0, (int) ($payload['questions_total'] ?? 0));
            $correct = max(0, (int) ($payload['questions_correct'] ?? 0));
            $wrong   = max(0, (int) ($payload['questions_wrong'] ?? 0));
            $blank   = max(0, (int) ($payload['questions_blank'] ?? 0));

            if ($total <= 0) {
                throw new RuntimeException('Informe a quantidade total de questões.');
            }
            if ($correct + $wrong + $blank > $total) {
                throw new RuntimeException('Acertos + erros + em branco não pode ser maior que o total de questões.');
            }

            $date = (string) ($payload['attempted_at'] ?? date('Y-m-d'));
            if (strtotime($date) === false) {
                throw new RuntimeException('Informe uma data de realização válida.');
            }

            $model = new StudyResourceAttemptModel();
            $attemptId = $model->insert([
                'user_id'           => $this->userId(),
                'resource_id'       => $resource['id'],
                'attempted_at'      => date('Y-m-d H:i:s', strtotime($date)),
                'questions_total'   => $total,
                'questions_correct' => $correct,
                'questions_wrong'   => $wrong,
                'questions_blank'   => $blank,
                'duration_minutes'  => max(0, (int) ($payload['duration_minutes'] ?? 0)),
                'score_percentage'  => round($correct / $total * 100, 2),
                'notes'             => $payload['notes'] ?? null,
            ]);

            if (! $attemptId) {
                throw new RuntimeException(implode(' ', $model->errors() ?: ['Não foi possível registrar a tentativa.']));
            }

            /** @var \App\Services\Study\StudyProgressService $progress */
            $progress = service('studyProgress');
            $result   = $progress->registerQuestions($this->userId(), $total, $correct, date('Y-m-d', strtotime($date)));
            $badges   = $progress->checkBadges($this->userId());

            return $this->jsonResponse(true, [
                'attempt'    => $model->find($attemptId),
                'xp_awarded' => $result['xp_awarded'],
                'new_badges' => $badges,
            ], 'Prova registrada como realizada!');
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }

    // ------------------------------------------------------------------

    /**
     * Normaliza e valida os campos do material vindos do payload.
     */
    private function resourceData(array $payload): array
    {
        $type = (string) ($payload['resource_type'] ?? 'official_page');

        if (! in_array($type, self::RESOURCE_TYPES, true)) {
            throw new RuntimeException('Tipo de material inválido.');
        }

        return [
            'year'          => (int) ($payload['year'] ?? 0),
            'organizer'     => trim((string) ($payload['organizer'] ?? '')),
            'title'         => trim((string) ($payload['title'] ?? '')),
            'description'   => trim((string) ($payload['description'] ?? '')) ?: null,
            'resource_type' => $type,
            'url'           => trim((string) ($payload['url'] ?? '')),
            'is_official'   => ! empty($payload['is_official']) ? 1 : 0,
        ];
    }

    private function findResource(int $id): array
    {
        $resource = (new StudyExamResourceModel())->find($id);

        if ($resource === null) {
            throw new RuntimeException('Material não encontrado.');
        }

        return $resource;
    }

    private function examId(): int
    {
        $exam = (new StudyExamModel())->where('active', 1)->orderBy('id', 'DESC')->first()
            ?? (new StudyExamModel())->orderBy('id', 'ASC')->first();

        if ($exam === null) {
            throw new RuntimeException('Nenhum concurso cadastrado para vincular o material.');
        }

        return (int) $exam['id'];
    }

    private function nextSortOrder(): int
    {
        $row = (new StudyExamResourceModel())
            ->selectMax('sort_order')
            ->withDeleted()
            ->first();

        return (int) ($row['sort_order'] ?? 0) + 1;
    }
}
