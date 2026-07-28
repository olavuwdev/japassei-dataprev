<?php

declare(strict_types=1);

namespace App\Controllers\Estudos;

use App\Controllers\BaseController;
use App\Models\StudyExamResourceModel;
use App\Models\StudySubjectModel;
use App\Models\StudyTopicModel;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * Registro de desempenho em questões e listagem dos registros.
 */
class QuestoesController extends BaseController
{
    public function index(): string
    {
        $userId = $this->userId();

        $subjects = (new StudySubjectModel())
            ->where('active', 1)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();

        $topics = (new StudyTopicModel())
            ->where('active', 1)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();

        $topicsBySubject = [];
        foreach ($topics as $topic) {
            $topicsBySubject[(int) $topic['subject_id']][] = [
                'id'   => (int) $topic['id'],
                'name' => $topic['name'],
            ];
        }

        $resources = (new StudyExamResourceModel())
            ->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->findAll();

        $statistics = service('studyStatistics');

        return view('estudos/questoes', [
            'subjects'          => $subjects,
            'topicsBySubject'   => $topicsBySubject,
            'resources'         => $resources,
            'attempts'          => $statistics->listAttempts($userId, [], 100),
            'accuracyBySubject' => $statistics->accuracyBySubject($userId),
            'accuracyEvolution' => $statistics->accuracyEvolution($userId),
        ]);
    }

    public function store(): ResponseInterface
    {
        $payload = (array) $this->request->getJSON(true);

        try {
            $result = service('studyStatistics')->registerAttempt($this->userId(), $payload);

            return $this->jsonResponse(true, $result, 'Desempenho registrado com sucesso!');
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }

    public function update(int $id): ResponseInterface
    {
        $payload = (array) $this->request->getJSON(true);

        try {
            $attempt = service('studyStatistics')->updateAttempt($this->userId(), $id, $payload);

            return $this->jsonResponse(true, ['attempt' => $attempt], 'Registro atualizado.');
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }

    public function delete(int $id): ResponseInterface
    {
        try {
            service('studyStatistics')->deleteAttempt($this->userId(), $id);

            return $this->jsonResponse(true, [], 'Registro excluído.');
        } catch (RuntimeException $e) {
            return $this->jsonResponse(false, [], $e->getMessage(), 422);
        }
    }
}
