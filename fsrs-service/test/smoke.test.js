/**
 * Testes do serviço FSRS. Executa com `npm test` (node:test, sem dependências).
 */

import assert from 'node:assert/strict';
import test from 'node:test';

import {
    applyRating,
    emptyCard,
    intervalLabel,
    preview,
    rebuild,
    retrievability,
    rollback,
    toCard,
    toLog,
} from '../src/fsrs.js';

const NOW = new Date('2026-07-29T10:00:00Z');
const PARAMS = {
    request_retention: 0.9,
    maximum_interval: 36500,
    enable_fuzz: false,
    enable_short_term: true,
    learning_steps: ['1m', '10m'],
    relearning_steps: ['10m'],
};

test('cartão novo começa no estado Novo, sem revisões', () => {
    const card = emptyCard(NOW);

    assert.equal(card.state, 0);
    assert.equal(card.reps, 0);
    assert.equal(card.lapses, 0);
    assert.equal(card.last_review, null);
});

test('preview devolve as quatro avaliações em ordem crescente de intervalo', () => {
    const result = preview(toCard({ state: 0, due: NOW.toISOString() }, NOW), NOW, PARAMS);

    assert.deepEqual(Object.keys(result).map(Number), [1, 2, 3, 4]);

    const dues = [1, 2, 3, 4].map((rating) => new Date(result[rating].due).getTime());
    assert.ok(dues[0] <= dues[1], 'Again deve vir antes de Hard');
    assert.ok(dues[1] <= dues[2], 'Hard deve vir antes de Good');
    assert.ok(dues[2] <= dues[3], 'Good deve vir antes de Easy');

    for (const rating of [1, 2, 3, 4]) {
        assert.equal(typeof result[rating].interval_label, 'string');
        assert.notEqual(result[rating].interval_label, '');
    }
});

test('preview não altera o cartão de origem', () => {
    const card = toCard({ state: 0, due: NOW.toISOString() }, NOW);
    const before = JSON.stringify(card);

    preview(card, NOW, PARAMS);

    assert.equal(JSON.stringify(card), before);
});

test('novo cartão avaliado como Bom entra em aprendizado', () => {
    const { card, log } = applyRating(toCard({ state: 0, due: NOW.toISOString() }, NOW), 3, NOW, PARAMS);

    assert.equal(card.reps, 1);
    assert.equal(log.rating, 3);
    assert.ok(new Date(card.due) > NOW);
});

test('cartão de revisão avaliado como Não lembrei vira reaprendizado e conta lapso', () => {
    const review = {
        due: '2026-07-29T10:00:00Z',
        stability: 20,
        difficulty: 5,
        elapsed_days: 20,
        scheduled_days: 20,
        reps: 5,
        lapses: 0,
        state: 2,
        last_review: '2026-07-09T10:00:00Z',
    };

    const { card } = applyRating(toCard(review, NOW), 1, NOW, PARAMS);

    assert.equal(card.state, 3);
    assert.equal(card.lapses, 1);
});

test('rollback restaura o estado anterior', () => {
    const original = toCard(
        {
            due: '2026-07-29T10:00:00Z',
            stability: 20,
            difficulty: 5,
            elapsed_days: 20,
            scheduled_days: 20,
            reps: 5,
            lapses: 0,
            state: 2,
            last_review: '2026-07-09T10:00:00Z',
        },
        NOW
    );

    const { card, log } = applyRating(original, 3, NOW, PARAMS);
    const restored = rollback(toCard(card), toLog(log), PARAMS);

    assert.equal(restored.reps, original.reps);
    assert.equal(restored.state, original.state);
    assert.equal(restored.lapses, original.lapses);
});

test('recuperabilidade fica entre 0 e 1 e cai com o tempo', () => {
    const card = toCard(
        {
            due: '2026-08-08T10:00:00Z',
            stability: 10,
            difficulty: 5,
            reps: 3,
            state: 2,
            last_review: '2026-07-29T10:00:00Z',
        },
        NOW
    );

    const soon = retrievability(card, new Date('2026-07-30T10:00:00Z'), PARAMS);
    const later = retrievability(card, new Date('2026-09-30T10:00:00Z'), PARAMS);

    assert.ok(soon > 0 && soon <= 1, `esperado 0<r<=1, recebido ${soon}`);
    assert.ok(later >= 0 && later <= 1);
    assert.ok(later < soon, 'a recuperabilidade deve cair com o tempo');
});

test('rebuild reproduz o estado a partir do histórico', () => {
    const { card, logs } = rebuild(
        [
            { rating: 3, review: '2026-07-01T10:00:00Z' },
            { rating: 3, review: '2026-07-02T10:00:00Z' },
            { rating: 1, review: '2026-07-10T10:00:00Z' },
        ],
        PARAMS
    );

    assert.equal(logs.length, 3);
    assert.equal(card.reps, 3);
    assert.equal(card.lapses, 1);
});

test('avaliação inválida é rejeitada', () => {
    assert.throws(() => applyRating(toCard({}, NOW), 9, NOW, PARAMS), TypeError);
});

test('rótulos de intervalo em português', () => {
    assert.equal(intervalLabel(NOW, new Date(NOW.getTime() + 10 * 60000)), '10 min');
    assert.equal(intervalLabel(NOW, new Date(NOW.getTime() + 86400000)), '1 dia');
    assert.equal(intervalLabel(NOW, new Date(NOW.getTime() + 6 * 86400000)), '6 dias');
    assert.equal(intervalLabel(NOW, new Date(NOW.getTime() + 90 * 86400000)), '3 meses');
});
