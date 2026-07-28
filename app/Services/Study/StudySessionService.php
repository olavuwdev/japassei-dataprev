<?php

declare(strict_types=1);

namespace App\Services\Study;

use App\Models\StudySessionModel;
use App\Models\StudyTaskModel;
use RuntimeException;

/**
 * Ciclo de vida das sessões de estudo (timer persistido no backend).
 * Um usuário nunca possui duas sessões em execução simultaneamente.
 */
class StudySessionService
{
    /**
     * Sessão ativa (em execução ou pausada) do usuário, com tempo decorrido.
     */
    public function getActive(int $userId): ?array
    {
        $session = (new StudySessionModel())
            ->where('user_id', $userId)
            ->whereIn('status', ['running', 'paused'])
            ->orderBy('id', 'DESC')
            ->first();

        if ($session === null) {
            return null;
        }

        $session['elapsed_seconds'] = $this->elapsedSeconds($session);

        return $session;
    }

    /**
     * Inicia sessão. $data aceita: task_id, subject_id, topic_id, session_type, planned_minutes.
     */
    public function start(int $userId, array $data): array
    {
        if ($this->getActive($userId) !== null) {
            throw new RuntimeException('Você já possui uma sessão em andamento. Conclua ou cancele antes de iniciar outra.');
        }

        $taskId    = isset($data['task_id']) ? (int) $data['task_id'] : null;
        $subjectId = isset($data['subject_id']) ? (int) $data['subject_id'] : null;
        $topicId   = isset($data['topic_id']) ? (int) $data['topic_id'] : null;
        $planned   = (int) ($data['planned_minutes'] ?? 60);
        $type      = $data['session_type'] ?? 'study';

        if ($taskId !== null) {
            $task = (new StudyTaskModel())->where('user_id', $userId)->find($taskId);
            if ($task === null) {
                throw new RuntimeException('Tarefa não encontrada.');
            }
            $subjectId = (int) $task['subject_id'];
            $topicId   = $task['topic_id'] !== null ? (int) $task['topic_id'] : null;
            $planned   = (int) $task['estimated_minutes'];

            if ($task['status'] === 'pending') {
                (new StudyTaskModel())->update($taskId, ['status' => 'in_progress']);
            }
        }

        if ($subjectId === null) {
            throw new RuntimeException('Informe a tarefa ou a disciplina da sessão.');
        }

        $now   = date('Y-m-d H:i:s');
        $model = new StudySessionModel();
        $id    = $model->insert([
            'user_id'          => $userId,
            'task_id'          => $taskId,
            'subject_id'       => $subjectId,
            'topic_id'         => $topicId,
            'session_type'     => $type,
            'started_at'       => $now,
            'duration_seconds' => 0,
            'planned_minutes'  => $planned,
            'status'           => 'running',
            'last_resumed_at'  => $now,
        ]);

        $session = $model->find($id);
        $session['elapsed_seconds'] = 0;

        return $session;
    }

    public function pause(int $userId, int $sessionId): array
    {
        $session = $this->findOwned($userId, $sessionId);

        if ($session['status'] !== 'running') {
            throw new RuntimeException('A sessão não está em execução.');
        }

        (new StudySessionModel())->update($sessionId, [
            'duration_seconds' => $this->elapsedSeconds($session),
            'status'           => 'paused',
            'last_resumed_at'  => null,
        ]);

        return $this->refresh($sessionId);
    }

    public function resume(int $userId, int $sessionId): array
    {
        $session = $this->findOwned($userId, $sessionId);

        if ($session['status'] !== 'paused') {
            throw new RuntimeException('A sessão não está pausada.');
        }

        (new StudySessionModel())->update($sessionId, [
            'status'          => 'running',
            'last_resumed_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->refresh($sessionId);
    }

    /**
     * Conclui a sessão: grava duração, atualiza tarefa, progresso diário,
     * ofensiva, XP e conquistas. Retorna resumo completo.
     */
    public function finish(int $userId, int $sessionId, array $data = []): array
    {
        $session = $this->findOwned($userId, $sessionId);

        if (! in_array($session['status'], ['running', 'paused'], true)) {
            throw new RuntimeException('A sessão já foi encerrada.');
        }

        $db = db_connect();
        $db->transException(true)->transStart();

        $seconds = $this->elapsedSeconds($session);
        $minutes = (int) floor($seconds / 60);

        (new StudySessionModel())->update($sessionId, [
            'duration_seconds' => $seconds,
            'status'           => 'completed',
            'ended_at'         => date('Y-m-d H:i:s'),
            'last_resumed_at'  => null,
            'notes'            => $data['notes'] ?? $session['notes'],
        ]);

        $taskId = $session['task_id'] !== null ? (int) $session['task_id'] : null;
        $date   = date('Y-m-d', strtotime($session['started_at']));

        /** @var StudyProgressService $progress */
        $progress = service('studyProgress');
        $result   = $this->applyMinutes($progress, $userId, $minutes, $date, $taskId);

        $db->transComplete();

        $badges = $progress->checkBadges($userId);

        return [
            'session'          => $this->refresh($sessionId),
            'duration_seconds' => $seconds,
            'duration_minutes' => $minutes,
            'xp_awarded'       => $result['xp_awarded'],
            'goal_met_now'     => $result['goal_met_now'],
            'streak'           => service('studyStreak')->getState($userId),
            'daily'            => $result['daily'],
            'new_badges'       => $badges,
        ];
    }

    public function cancel(int $userId, int $sessionId): array
    {
        $session = $this->findOwned($userId, $sessionId);

        if (! in_array($session['status'], ['running', 'paused'], true)) {
            throw new RuntimeException('A sessão já foi encerrada.');
        }

        (new StudySessionModel())->update($sessionId, [
            'duration_seconds' => $this->elapsedSeconds($session),
            'status'           => 'cancelled',
            'ended_at'         => date('Y-m-d H:i:s'),
            'last_resumed_at'  => null,
        ]);

        // Sessão cancelada não conta minutos: se a tarefa estava "em estudo"
        // sem outra sessão concluída, ela permanece in_progress mesmo.
        return $this->refresh($sessionId);
    }

    /**
     * Exclui logicamente uma sessão concluída e reprocessa o dia (pode
     * desqualificar a meta e recalcular a ofensiva).
     */
    public function delete(int $userId, int $sessionId): void
    {
        $session = $this->findOwned($userId, $sessionId);
        $minutes = (int) floor((int) $session['duration_seconds'] / 60);
        $date    = date('Y-m-d', strtotime($session['started_at']));

        (new StudySessionModel())->delete($sessionId);

        if ($session['status'] === 'completed' && $minutes > 0) {
            /** @var StudyProgressService $progress */
            $progress = service('studyProgress');
            $daily    = $progress->getOrCreateDaily($userId, $date);

            (new \App\Models\StudyDailyProgressModel())->update($daily['id'], [
                'studied_minutes' => max(0, (int) $daily['studied_minutes'] - $minutes),
            ]);

            if ($session['task_id'] !== null) {
                $task = (new StudyTaskModel())->find((int) $session['task_id']);
                if ($task !== null) {
                    (new StudyTaskModel())->update((int) $session['task_id'], [
                        'actual_minutes' => max(0, (int) $task['actual_minutes'] - $minutes),
                    ]);
                }
            }

            $progress->evaluateDailyGoal($userId, $date);
        }
    }

    public function elapsedSeconds(array $session): int
    {
        $base = (int) $session['duration_seconds'];

        if ($session['status'] === 'running' && ! empty($session['last_resumed_at'])) {
            $base += max(0, time() - strtotime($session['last_resumed_at']));
        }

        return $base;
    }

    private function applyMinutes(StudyProgressService $progress, int $userId, int $minutes, string $date, ?int $taskId): array
    {
        // addStudyMinutes usa actual_minutes ANTES do incremento para o teto de
        // XP por tarefa; por isso os minutos da tarefa são somados só depois.
        if ($taskId !== null) {
            $task = (new StudyTaskModel())->find($taskId);
            if ($task !== null) {
                $result = $progress->addStudyMinutes($userId, $minutes, $date, $taskId);
                (new StudyTaskModel())->update($taskId, [
                    'actual_minutes' => (int) $task['actual_minutes'] + $minutes,
                ]);

                return $result;
            }
        }

        return $progress->addStudyMinutes($userId, $minutes, $date, null);
    }

    private function findOwned(int $userId, int $sessionId): array
    {
        $session = (new StudySessionModel())->where('user_id', $userId)->find($sessionId);

        if ($session === null) {
            throw new RuntimeException('Sessão não encontrada.');
        }

        return $session;
    }

    private function refresh(int $sessionId): array
    {
        $session = (new StudySessionModel())->find($sessionId);
        $session['elapsed_seconds'] = $this->elapsedSeconds($session);

        return $session;
    }
}
