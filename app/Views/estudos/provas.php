<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Provas antigas<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Provas antigas<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$typeLabels = [
    'official_page' => 'Página oficial',
    'exams_answers' => 'Provas e gabaritos',
    'answer_key'    => 'Gabarito',
];
?>

<div class="page-header">
    <div>
        <h1>Provas antigas</h1>
        <p class="subtitle">Materiais oficiais dos concursos anteriores da Dataprev para treinar de verdade.</p>
    </div>
    <button type="button" class="btn btn-primary" id="new-resource-btn">+ Novo material</button>
</div>

<div class="flash flash-warning" role="note">
    Links podem mudar; se algum não abrir, edite o material com a nova URL.
</div>

<?php if ($resources === []): ?>
    <div class="card">
        <div class="empty-state">
            <span class="empty-state-icon">📄</span>
            <p>Nenhum material cadastrado. Use o botão "Novo material" para adicionar links de provas antigas.</p>
        </div>
    </div>
<?php else: ?>
    <div class="grid grid-3">
        <?php foreach ($resources as $resource): ?>
            <?php
            $editData = [
                'id'            => (int) $resource['id'],
                'year'          => (int) $resource['year'],
                'organizer'     => $resource['organizer'],
                'title'         => $resource['title'],
                'description'   => $resource['description'],
                'resource_type' => $resource['resource_type'],
                'url'           => $resource['url'],
                'is_official'   => (int) $resource['is_official'] === 1,
            ];
            $myAttempts = $attemptsByResource[(int) $resource['id']] ?? [];
            ?>
            <div class="card mt-0">
                <div class="flex flex-wrap items-center gap-1 mb-1">
                    <span class="chip chip-flame"><?= esc($resource['year']) ?></span>
                    <span class="chip"><?= esc($resource['organizer']) ?></span>
                    <span class="chip chip-info"><?= esc($typeLabels[$resource['resource_type']] ?? $resource['resource_type']) ?></span>
                    <?php if ((int) $resource['is_official'] === 1): ?>
                        <span class="chip chip-gold">✔ Fonte oficial</span>
                    <?php endif; ?>
                </div>

                <h3 class="mb-1"><?= esc($resource['title']) ?></h3>

                <?php if (! empty($resource['description'])): ?>
                    <p class="text-small text-muted"><?= esc($resource['description']) ?></p>
                <?php endif; ?>

                <div class="flex flex-wrap gap-1 mt-1">
                    <a class="btn btn-primary btn-sm" href="<?= esc($resource['url'], 'attr') ?>"
                       target="_blank" rel="noopener noreferrer">Acessar</a>
                    <button type="button" class="btn btn-flame btn-sm"
                            data-resource-attempt="<?= esc($resource['id'], 'attr') ?>"
                            data-label="<?= esc($resource['year'] . ' · ' . $resource['organizer'] . ' — ' . $resource['title'], 'attr') ?>">
                        Marcar como realizado
                    </button>
                </div>

                <div class="flex flex-wrap gap-1 mt-1">
                    <button type="button" class="btn btn-ghost btn-sm"
                            data-resource-edit="<?= esc(json_encode($editData, JSON_UNESCAPED_UNICODE), 'attr') ?>">Editar</button>
                    <button type="button" class="btn btn-ghost btn-sm"
                            data-resource-deactivate="<?= esc($resource['id'], 'attr') ?>">Desativar</button>
                    <button type="button" class="btn btn-danger btn-sm"
                            data-resource-delete="<?= esc($resource['id'], 'attr') ?>">Excluir</button>
                </div>

                <?php if ($myAttempts !== []): ?>
                    <div class="mt-2" style="border-top: 1px solid var(--border); padding-top: 10px;">
                        <div class="text-small fw-bold mb-1">Minhas tentativas</div>
                        <?php foreach ($myAttempts as $attempt): ?>
                            <div class="text-small text-muted">
                                <?= esc(date('d/m/Y', strtotime($attempt['attempted_at']))) ?> —
                                <?= esc($attempt['questions_correct']) ?>/<?= esc($attempt['questions_total']) ?> acertos
                                (<?= esc(round((float) $attempt['score_percentage'], 1)) ?>%)
                                <?php if ((int) $attempt['duration_minutes'] > 0): ?>
                                    · <?= esc($attempt['duration_minutes']) ?> min
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    'use strict';

    const API = '<?= site_url('estudos/api/provas') ?>';

    function reloadSoon(delay) {
        setTimeout(() => window.location.reload(), delay || 800);
    }

    // ------------------------------------------------------------------
    // Formulário de material (novo / edição)
    // ------------------------------------------------------------------
    function openResourceModal(resource) {
        const isEdit = !!resource;
        const modal = JP.openModal(
            '<h3 class="modal-title">' + (isEdit ? 'Editar material' : 'Novo material') + '</h3>' +
            '<form data-resource-form>' +
            '  <div class="form-row">' +
            '    <div class="field"><label>Ano</label><input type="number" name="year" min="1990" max="2100" required></div>' +
            '    <div class="field"><label>Organizadora</label><input type="text" name="organizer" required placeholder="Ex.: Cebraspe"></div>' +
            '  </div>' +
            '  <div class="field"><label>Título</label><input type="text" name="title" required minlength="3"></div>' +
            '  <div class="field"><label>Descrição (opcional)</label><textarea name="description" rows="2"></textarea></div>' +
            '  <div class="field"><label>Tipo</label>' +
            '    <select name="resource_type">' +
            '      <option value="official_page">Página oficial</option>' +
            '      <option value="exams_answers">Provas e gabaritos</option>' +
            '      <option value="answer_key">Gabarito</option>' +
            '    </select></div>' +
            '  <div class="field"><label>URL</label><input type="url" name="url" required placeholder="https://…"></div>' +
            '  <div class="field"><label class="checkbox-row"><input type="checkbox" name="is_official"> Fonte oficial</label></div>' +
            '  <div class="modal-actions">' +
            '    <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>' +
            '    <button type="submit" class="btn btn-primary">' + (isEdit ? 'Salvar alterações' : 'Cadastrar') + '</button>' +
            '  </div>' +
            '</form>'
        );

        const form = modal.el.querySelector('[data-resource-form]');

        if (isEdit) {
            form.year.value = resource.year;
            form.organizer.value = resource.organizer;
            form.title.value = resource.title;
            form.description.value = resource.description || '';
            form.resource_type.value = resource.resource_type;
            form.url.value = resource.url;
            form.is_official.checked = resource.is_official;
        } else {
            form.year.value = new Date().getFullYear();
            form.is_official.checked = true;
        }

        form.addEventListener('submit', async (ev) => {
            ev.preventDefault();
            const url = isEdit ? API + '/' + resource.id + '/editar' : API;
            const res = await JP.api(url, {
                method: 'POST',
                body: {
                    year: parseInt(form.year.value || '0', 10),
                    organizer: form.organizer.value.trim(),
                    title: form.title.value.trim(),
                    description: form.description.value.trim() || null,
                    resource_type: form.resource_type.value,
                    url: form.url.value.trim(),
                    is_official: form.is_official.checked
                }
            });

            if (!res.ok) {
                JP.toast(res.message || 'Não foi possível salvar o material.', 'error');
                return;
            }

            JP.toast(res.message, 'success');
            modal.close();
            reloadSoon();
        });
    }

    document.getElementById('new-resource-btn').addEventListener('click', () => openResourceModal(null));

    document.querySelectorAll('[data-resource-edit]').forEach((btn) => {
        btn.addEventListener('click', () => openResourceModal(JSON.parse(btn.dataset.resourceEdit)));
    });

    // ------------------------------------------------------------------
    // Desativar / excluir
    // ------------------------------------------------------------------
    document.querySelectorAll('[data-resource-deactivate]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const confirmed = await JP.confirmDialog('Desativar este material? Ele deixará de aparecer na lista.', { okLabel: 'Desativar' });
            if (!confirmed) { return; }

            const res = await JP.api(API + '/' + btn.dataset.resourceDeactivate + '/desativar', { method: 'POST' });
            if (!res.ok) { JP.toast(res.message || 'Não foi possível desativar.', 'error'); return; }
            JP.toast(res.message, 'success');
            reloadSoon();
        });
    });

    document.querySelectorAll('[data-resource-delete]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const confirmed = await JP.confirmDialog('Excluir este material?', { danger: true, okLabel: 'Excluir' });
            if (!confirmed) { return; }

            const res = await JP.api(API + '/' + btn.dataset.resourceDelete + '/excluir', { method: 'POST' });
            if (!res.ok) { JP.toast(res.message || 'Não foi possível excluir.', 'error'); return; }
            JP.toast(res.message, 'success');
            reloadSoon();
        });
    });

    // ------------------------------------------------------------------
    // Marcar como realizado (tentativa)
    // ------------------------------------------------------------------
    document.querySelectorAll('[data-resource-attempt]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.resourceAttempt;
            const modal = JP.openModal(
                '<h3 class="modal-title">Marcar como realizado</h3>' +
                '<p class="text-small text-muted" data-attempt-label></p>' +
                '<form data-attempt-form>' +
                '  <div class="field"><label>Data de realização</label><input type="date" name="attempted_at" required></div>' +
                '  <div class="form-row">' +
                '    <div class="field"><label>Total de questões</label><input type="number" name="questions_total" min="1" value="0" required></div>' +
                '    <div class="field"><label>Acertos</label><input type="number" name="questions_correct" min="0" value="0" required></div>' +
                '  </div>' +
                '  <div class="form-row">' +
                '    <div class="field"><label>Erros</label><input type="number" name="questions_wrong" min="0" value="0"></div>' +
                '    <div class="field"><label>Em branco</label><input type="number" name="questions_blank" min="0" value="0"></div>' +
                '  </div>' +
                '  <div class="form-row">' +
                '    <div class="field"><label>Tempo (minutos)</label><input type="number" name="duration_minutes" min="0" value="0"></div>' +
                '    <div class="field"><label>Nota (%)</label><input type="text" data-attempt-score value="0%" readonly></div>' +
                '  </div>' +
                '  <div class="field"><label>Observação (opcional)</label><textarea name="notes" rows="2"></textarea></div>' +
                '  <div class="modal-actions">' +
                '    <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>' +
                '    <button type="submit" class="btn btn-primary">Registrar</button>' +
                '  </div>' +
                '</form>'
            );

            modal.el.querySelector('[data-attempt-label]').textContent = btn.dataset.label || '';

            const form = modal.el.querySelector('[data-attempt-form]');
            form.attempted_at.value = new Date().toISOString().slice(0, 10);

            const scoreField = modal.el.querySelector('[data-attempt-score]');
            function refreshScore() {
                const total = parseInt(form.questions_total.value || '0', 10);
                const correct = parseInt(form.questions_correct.value || '0', 10);
                scoreField.value = (total > 0 ? Math.round(correct / total * 1000) / 10 : 0) + '%';
            }
            form.questions_total.addEventListener('input', refreshScore);
            form.questions_correct.addEventListener('input', refreshScore);

            form.addEventListener('submit', async (ev) => {
                ev.preventDefault();
                const res = await JP.api(API + '/' + id + '/tentativa', {
                    method: 'POST',
                    body: {
                        attempted_at: form.attempted_at.value,
                        questions_total: parseInt(form.questions_total.value || '0', 10),
                        questions_correct: parseInt(form.questions_correct.value || '0', 10),
                        questions_wrong: parseInt(form.questions_wrong.value || '0', 10),
                        questions_blank: parseInt(form.questions_blank.value || '0', 10),
                        duration_minutes: parseInt(form.duration_minutes.value || '0', 10),
                        notes: form.notes.value.trim() || null
                    }
                });

                if (!res.ok) {
                    JP.toast(res.message || 'Não foi possível registrar.', 'error');
                    return;
                }

                JP.toast(res.message, 'success');
                if (res.data && res.data.xp_awarded > 0) { JP.xpPop(res.data.xp_awarded, btn); }
                if (res.data && res.data.new_badges && res.data.new_badges.length > 0) { JP.celebrate(); }
                modal.close();
                reloadSoon(900);
            });
        });
    });
})();
</script>
<?= $this->endSection() ?>
