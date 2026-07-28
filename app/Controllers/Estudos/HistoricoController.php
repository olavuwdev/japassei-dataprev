<?php

declare(strict_types=1);

namespace App\Controllers\Estudos;

use App\Controllers\BaseController;
use App\Models\StudyStreakHistoryModel;

/**
 * Histórico de sessões, registros de questões e eventos da ofensiva.
 */
class HistoricoController extends BaseController
{
    private const PER_PAGE = 50;

    public function index(): string
    {
        $userId = $this->userId();
        $page   = max(1, (int) $this->request->getGet('page'));
        $offset = ($page - 1) * self::PER_PAGE;

        $db = db_connect();

        // Sessões concluídas ou canceladas
        $sessionsBase = static fn () => $db->table('study_sessions ss')
            ->join('study_subjects s', 's.id = ss.subject_id')
            ->join('study_topics t', 't.id = ss.topic_id', 'left')
            ->where('ss.user_id', $userId)
            ->whereIn('ss.status', ['completed', 'cancelled'])
            ->where('ss.deleted_at', null);

        $sessionsTotal = $sessionsBase()->countAllResults();
        $sessions      = $sessionsBase()
            ->select('ss.*, s.name AS subject_name, s.color AS subject_color, t.name AS topic_name')
            ->orderBy('ss.started_at', 'DESC')
            ->limit(self::PER_PAGE, $offset)
            ->get()
            ->getResultArray();

        // Registros de questões
        $attemptsBase = static fn () => $db->table('study_question_attempts a')
            ->join('study_subjects s', 's.id = a.subject_id')
            ->join('study_topics t', 't.id = a.topic_id', 'left')
            ->where('a.user_id', $userId)
            ->where('a.deleted_at', null);

        $attemptsTotal = $attemptsBase()->countAllResults();
        $attempts      = $attemptsBase()
            ->select('a.*, s.name AS subject_name, s.color AS subject_color, t.name AS topic_name')
            ->orderBy('a.attempt_date', 'DESC')
            ->orderBy('a.id', 'DESC')
            ->limit(self::PER_PAGE, $offset)
            ->get()
            ->getResultArray();

        // Histórico da ofensiva
        $streakModel = new StudyStreakHistoryModel();
        $streakTotal = $streakModel->where('user_id', $userId)->countAllResults();
        $streakRows  = $streakModel
            ->where('user_id', $userId)
            ->orderBy('reference_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll(self::PER_PAGE, $offset);

        $maxTotal = max($sessionsTotal, $attemptsTotal, $streakTotal);

        return view('estudos/historico', [
            'sessions'      => $sessions,
            'sessionsTotal' => $sessionsTotal,
            'attempts'      => $attempts,
            'attemptsTotal' => $attemptsTotal,
            'streakRows'    => $streakRows,
            'streakTotal'   => $streakTotal,
            'page'          => $page,
            'totalPages'    => max(1, (int) ceil($maxTotal / self::PER_PAGE)),
        ]);
    }
}
