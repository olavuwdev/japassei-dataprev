<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Questões<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Questões<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$scoreChip = static function (float $score): string {
    if ($score >= 80) {
        return 'chip-primary';
    }

    return $score >= 60 ? 'chip-gold' : 'chip-danger';
};
?>

<div class="page-header">
    <div>
        <h1>Questões</h1>
        <p class="subtitle">Registre seu desempenho e acompanhe a evolução por disciplina.</p>
    </div>
</div>

<div class="flex flex-wrap gap-1 mb-2" role="tablist" aria-label="Áreas de questões">
    <button type="button" class="btn btn-sm btn-primary" data-tab-btn="registrar" role="tab" aria-selected="true">Registrar desempenho</button>
    <button type="button" class="btn btn-sm btn-ghost" data-tab-btn="registros" role="tab" aria-selected="false">Meus registros</button>
</div>

<!-- Aba A: Registrar desempenho -->
<div data-tab-panel="registrar">
    <div class="card">
        <div class="card-header"><h3>✍️ Registrar desempenho</h3></div>
        <form id="attempt-form">
            <div class="form-row">
                <div class="field">
                    <label for="q-date">Data</label>
                    <input type="date" id="q-date" name="attempt_date" value="<?= esc(date('Y-m-d'), 'attr') ?>" required>
                </div>
                <div class="field">
                    <label for="q-subject">Disciplina</label>
                    <select id="q-subject" name="subject_id" required>
                        <option value="">Selecione…</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= esc($subject['id'], 'attr') ?>"><?= esc($subject['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="field">
                    <label for="q-topic">Conteúdo / tópico (opcional)</label>
                    <select id="q-topic" name="topic_id">
                        <option value="">Selecione a disciplina primeiro</option>
                    </select>
                </div>
                <div class="field">
                    <label for="q-source">Fonte (opcional)</label>
                    <input type="text" id="q-source" name="source" placeholder="Ex.: QConcursos, simulado, prova antiga…">
                </div>
            </div>
            <div class="field">
                <label for="q-resource">Prova / material (opcional)</label>
                <select id="q-resource" name="resource_id">
                    <option value="">Nenhum</option>
                    <?php foreach ($resources as $resource): ?>
                        <option value="<?= esc($resource['id'], 'attr') ?>">
                            <?= esc($resource['year']) ?> · <?= esc($resource['organizer']) ?> — <?= esc($resource['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="field">
                    <label for="q-total">Quantidade total</label>
                    <input type="number" id="q-total" name="questions_total" min="1" value="10" required>
                </div>
                <div class="field">
                    <label for="q-correct">Acertos</label>
                    <input type="number" id="q-correct" name="questions_correct" min="0" value="0" required>
                </div>
                <div class="field">
                    <label for="q-wrong">Erros</label>
                    <input type="number" id="q-wrong" name="questions_wrong" min="0" value="0" required>
                </div>
                <div class="field">
                    <label for="q-blank">Em branco</label>
                    <input type="number" id="q-blank" name="questions_blank" min="0" value="0" required>
                </div>
            </div>
            <div class="form-row">
                <div class="field">
                    <label for="q-duration">Tempo utilizado (minutos)</label>
                    <input type="number" id="q-duration" name="duration_minutes" min="0" value="0">
                </div>
                <div class="field">
                    <label>Aproveitamento</label>
                    <div class="progress-label"><span id="live-accuracy-text">0%</span><span>meta: 80%</span></div>
                    <div class="progress"><div class="progress-bar" id="live-accuracy-bar" style="width: 0%;"></div></div>
                </div>
            </div>
            <div class="field">
                <label for="q-errors">Assuntos com erro / observações (opcional)</label>
                <textarea id="q-errors" name="error_notes" rows="3" placeholder="Anote os assuntos em que errou para revisar depois."></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Salvar registro</button>
        </form>
    </div>
</div>

<!-- Aba B: Meus registros -->
<div data-tab-panel="registros" hidden>
    <div class="card">
        <div class="card-header"><h3>📋 Meus registros</h3></div>
        <?php if ($attempts === []): ?>
            <div class="empty-state">
                <span class="empty-state-icon">✍️</span>
                <p>Nenhum registro de questões ainda. Comece registrando seu primeiro desempenho!</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Disciplina</th>
                            <th>Total</th>
                            <th>Acertos</th>
                            <th>%</th>
                            <th>Fonte</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attempts as $attempt): ?>
                            <?php
                            $score = (float) ($attempt['score_percentage'] ?? 0);
                            $editData = [
                                'id'                => (int) $attempt['id'],
                                'attempt_date'      => $attempt['attempt_date'],
                                'subject_id'        => (int) $attempt['subject_id'],
                                'topic_id'          => $attempt['topic_id'] !== null ? (int) $attempt['topic_id'] : null,
                                'resource_id'       => $attempt['resource_id'] !== null ? (int) $attempt['resource_id'] : null,
                                'source'            => $attempt['source'],
                                'questions_total'   => (int) $attempt['questions_total'],
                                'questions_correct' => (int) $attempt['questions_correct'],
                                'questions_wrong'   => (int) $attempt['questions_wrong'],
                                'questions_blank'   => (int) $attempt['questions_blank'],
                                'duration_minutes'  => (int) $attempt['duration_minutes'],
                                'error_notes'       => $attempt['error_notes'],
                            ];
                            ?>
                            <tr>
                                <td><?= esc(date('d/m/Y', strtotime($attempt['attempt_date']))) ?></td>
                                <td>
                                    <span class="chip">
                                        <span class="chip-dot" style="background: <?= esc($attempt['subject_color'] ?: '#1B7A5E', 'attr') ?>;"></span>
                                        <?= esc($attempt['subject_name']) ?>
                                    </span>
                                    <?php if (! empty($attempt['topic_name'])): ?>
                                        <div class="text-small text-faint"><?= esc($attempt['topic_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= esc($attempt['questions_total']) ?></td>
                                <td><?= esc($attempt['questions_correct']) ?></td>
                                <td><span class="chip <?= esc($scoreChip($score), 'attr') ?>"><?= esc(round($score, 1)) ?>%</span></td>
                                <td><?= esc($attempt['source'] ?: '—') ?></td>
                                <td>
                                    <div class="flex gap-1">
                                        <button type="button" class="btn btn-ghost btn-sm"
                                                data-attempt-edit="<?= esc(json_encode($editData, JSON_UNESCAPED_UNICODE), 'attr') ?>">Editar</button>
                                        <button type="button" class="btn btn-danger btn-sm"
                                                data-attempt-delete="<?= esc($attempt['id'], 'attr') ?>">Excluir</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="grid grid-2">
        <div class="card">
            <div class="card-header"><h3>📊 Média por disciplina</h3></div>
            <?php if ($accuracyBySubject['labels'] === []): ?>
                <div class="empty-state">
                    <span class="empty-state-icon">📊</span>
                    <p>Registre questões para ver suas médias por disciplina.</p>
                </div>
            <?php else: ?>
                <?php foreach ($accuracyBySubject['labels'] as $i => $label): ?>
                    <?php $value = (float) $accuracyBySubject['values'][$i]; ?>
                    <div class="mb-1">
                        <div class="progress-label">
                            <span><?= esc($label) ?></span>
                            <span><?= esc($value) ?>%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" style="width: <?= esc(min(100, $value), 'attr') ?>%; background: <?= esc($accuracyBySubject['colors'][$i], 'attr') ?>;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header"><h3>📈 Evolução dos acertos (por semana)</h3></div>
            <?php if ($accuracyEvolution['labels'] === []): ?>
                <div class="empty-state">
                    <span class="empty-state-icon">📈</span>
                    <p>Sem dados suficientes para mostrar a evolução ainda.</p>
                </div>
            <?php else: ?>
                <?php foreach ($accuracyEvolution['labels'] as $i => $label): ?>
                    <?php $value = (float) $accuracyEvolution['values'][$i]; ?>
                    <div class="mb-1">
                        <div class="progress-label">
                            <span>Semana de <?= esc($label) ?></span>
                            <span><?= esc($value) ?>%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar <?= $value >= 80 ? '' : ($value >= 60 ? 'is-gold' : 'is-flame') ?>"
                                 style="width: <?= esc(min(100, $value), 'attr') ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
window.JPQ = {
    api: '<?= site_url('estudos/api/questoes') ?>',
    subjects: <?= json_encode(array_map(static fn (array $s): array => ['id' => (int) $s['id'], 'name' => $s['name']], $subjects), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
    topicsBySubject: <?= json_encode($topicsBySubject !== [] ? $topicsBySubject : new stdClass(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
    resources: <?= json_encode(array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'label' => $r['year'] . ' · ' . $r['organizer'] . ' — ' . $r['title']], $resources), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script>
(function () {
    'use strict';

    // ------------------------------------------------------------------
    // Abas
    // ------------------------------------------------------------------
    const tabButtons = document.querySelectorAll('[data-tab-btn]');
    tabButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            tabButtons.forEach((b) => {
                const active = b === btn;
                b.classList.toggle('btn-primary', active);
                b.classList.toggle('btn-ghost', !active);
                b.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            document.querySelectorAll('[data-tab-panel]').forEach((panel) => {
                panel.hidden = panel.dataset.tabPanel !== btn.dataset.tabBtn;
            });
        });
    });

    // ------------------------------------------------------------------
    // Selects dependentes e % em tempo real (apenas exibição)
    // ------------------------------------------------------------------
    function fillTopicOptions(select, subjectId, selectedTopicId) {
        select.innerHTML = '';
        const topics = window.JPQ.topicsBySubject[subjectId] || [];
        select.appendChild(new Option(topics.length ? 'Nenhum (disciplina geral)' : 'Sem tópicos para esta disciplina', ''));
        topics.forEach((topic) => {
            const opt = new Option(topic.name, String(topic.id));
            if (selectedTopicId && Number(selectedTopicId) === topic.id) { opt.selected = true; }
            select.appendChild(opt);
        });
    }

    const subjectSelect = document.getElementById('q-subject');
    const topicSelect = document.getElementById('q-topic');
    subjectSelect.addEventListener('change', () => fillTopicOptions(topicSelect, subjectSelect.value, null));

    function updateLiveAccuracy() {
        const total = parseInt(document.getElementById('q-total').value || '0', 10);
        const correct = parseInt(document.getElementById('q-correct').value || '0', 10);
        const pct = total > 0 ? Math.min(100, Math.round(correct / total * 1000) / 10) : 0;
        document.getElementById('live-accuracy-text').textContent = pct + '%';
        document.getElementById('live-accuracy-bar').style.width = Math.min(100, pct) + '%';
    }
    ['q-total', 'q-correct'].forEach((id) => document.getElementById(id).addEventListener('input', updateLiveAccuracy));

    // ------------------------------------------------------------------
    // Novo registro
    // ------------------------------------------------------------------
    document.getElementById('attempt-form').addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const form = ev.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;

        const res = await JP.api(window.JPQ.api, {
            method: 'POST',
            body: {
                attempt_date: form.attempt_date.value,
                subject_id: parseInt(form.subject_id.value || '0', 10),
                topic_id: form.topic_id.value ? parseInt(form.topic_id.value, 10) : null,
                resource_id: form.resource_id.value ? parseInt(form.resource_id.value, 10) : null,
                source: form.source.value.trim() || null,
                questions_total: parseInt(form.questions_total.value || '0', 10),
                questions_correct: parseInt(form.questions_correct.value || '0', 10),
                questions_wrong: parseInt(form.questions_wrong.value || '0', 10),
                questions_blank: parseInt(form.questions_blank.value || '0', 10),
                duration_minutes: parseInt(form.duration_minutes.value || '0', 10),
                error_notes: form.error_notes.value.trim() || null
            }
        });

        submitBtn.disabled = false;

        if (!res.ok) {
            JP.toast(res.message || 'Não foi possível salvar o registro.', 'error');
            return;
        }

        JP.toast(res.message, 'success');
        if (res.data && res.data.xp_awarded > 0) { JP.xpPop(res.data.xp_awarded, submitBtn); }
        if (res.data && res.data.new_badges && res.data.new_badges.length > 0) { JP.celebrate(); }
        setTimeout(() => window.location.reload(), 900);
    });

    // ------------------------------------------------------------------
    // Editar registro
    // ------------------------------------------------------------------
    document.querySelectorAll('[data-attempt-edit]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const attempt = JSON.parse(btn.dataset.attemptEdit);
            const modal = JP.openModal(
                '<h3 class="modal-title">Editar registro</h3>' +
                '<form data-edit-form>' +
                '  <div class="form-row">' +
                '    <div class="field"><label>Data</label><input type="date" name="attempt_date" required></div>' +
                '    <div class="field"><label>Disciplina</label><select name="subject_id" required></select></div>' +
                '  </div>' +
                '  <div class="form-row">' +
                '    <div class="field"><label>Conteúdo / tópico</label><select name="topic_id"></select></div>' +
                '    <div class="field"><label>Fonte</label><input type="text" name="source"></div>' +
                '  </div>' +
                '  <div class="field"><label>Prova / material</label><select name="resource_id"></select></div>' +
                '  <div class="form-row">' +
                '    <div class="field"><label>Total</label><input type="number" name="questions_total" min="1" required></div>' +
                '    <div class="field"><label>Acertos</label><input type="number" name="questions_correct" min="0" required></div>' +
                '    <div class="field"><label>Erros</label><input type="number" name="questions_wrong" min="0" required></div>' +
                '    <div class="field"><label>Em branco</label><input type="number" name="questions_blank" min="0" required></div>' +
                '  </div>' +
                '  <div class="field"><label>Tempo (minutos)</label><input type="number" name="duration_minutes" min="0"></div>' +
                '  <div class="field"><label>Assuntos com erro / observações</label><textarea name="error_notes" rows="3"></textarea></div>' +
                '  <div class="modal-actions">' +
                '    <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>' +
                '    <button type="submit" class="btn btn-primary">Salvar alterações</button>' +
                '  </div>' +
                '</form>'
            );

            const form = modal.el.querySelector('[data-edit-form]');
            form.attempt_date.value = attempt.attempt_date;
            form.source.value = attempt.source || '';
            form.questions_total.value = attempt.questions_total;
            form.questions_correct.value = attempt.questions_correct;
            form.questions_wrong.value = attempt.questions_wrong;
            form.questions_blank.value = attempt.questions_blank;
            form.duration_minutes.value = attempt.duration_minutes;
            form.error_notes.value = attempt.error_notes || '';

            window.JPQ.subjects.forEach((subject) => {
                const opt = new Option(subject.name, String(subject.id));
                if (subject.id === attempt.subject_id) { opt.selected = true; }
                form.subject_id.appendChild(opt);
            });

            fillTopicOptions(form.topic_id, String(attempt.subject_id), attempt.topic_id);
            form.subject_id.addEventListener('change', () => fillTopicOptions(form.topic_id, form.subject_id.value, null));

            form.resource_id.appendChild(new Option('Nenhum', ''));
            window.JPQ.resources.forEach((resource) => {
                const opt = new Option(resource.label, String(resource.id));
                if (resource.id === attempt.resource_id) { opt.selected = true; }
                form.resource_id.appendChild(opt);
            });

            form.addEventListener('submit', async (ev) => {
                ev.preventDefault();
                const res = await JP.api(window.JPQ.api + '/' + attempt.id + '/editar', {
                    method: 'POST',
                    body: {
                        attempt_date: form.attempt_date.value,
                        subject_id: parseInt(form.subject_id.value, 10),
                        topic_id: form.topic_id.value ? parseInt(form.topic_id.value, 10) : null,
                        resource_id: form.resource_id.value ? parseInt(form.resource_id.value, 10) : null,
                        source: form.source.value.trim() || null,
                        questions_total: parseInt(form.questions_total.value || '0', 10),
                        questions_correct: parseInt(form.questions_correct.value || '0', 10),
                        questions_wrong: parseInt(form.questions_wrong.value || '0', 10),
                        questions_blank: parseInt(form.questions_blank.value || '0', 10),
                        duration_minutes: parseInt(form.duration_minutes.value || '0', 10),
                        error_notes: form.error_notes.value.trim() || null
                    }
                });

                if (!res.ok) {
                    JP.toast(res.message || 'Não foi possível atualizar.', 'error');
                    return;
                }

                JP.toast(res.message, 'success');
                modal.close();
                setTimeout(() => window.location.reload(), 700);
            });
        });
    });

    // ------------------------------------------------------------------
    // Excluir registro
    // ------------------------------------------------------------------
    document.querySelectorAll('[data-attempt-delete]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const confirmed = await JP.confirmDialog(
                'Excluir este registro de questões? Essa ação não pode ser desfeita.',
                { danger: true, okLabel: 'Excluir' }
            );
            if (!confirmed) { return; }

            const res = await JP.api(window.JPQ.api + '/' + btn.dataset.attemptDelete + '/excluir', { method: 'POST' });

            if (!res.ok) {
                JP.toast(res.message || 'Não foi possível excluir.', 'error');
                return;
            }

            JP.toast(res.message, 'success');
            setTimeout(() => window.location.reload(), 700);
        });
    });
})();
</script>
<?= $this->endSection() ?>
