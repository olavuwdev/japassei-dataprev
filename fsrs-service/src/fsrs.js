/**
 * Camada fina sobre a biblioteca ts-fsrs.
 *
 * Todo o cálculo de agendamento do sistema acontece aqui — o backend PHP nunca
 * calcula intervalos por conta própria.
 */

import {
    createEmptyCard,
    fsrs,
    generatorParameters,
    Rating,
    State,
} from 'ts-fsrs';

/** Avaliações aceitas: 1 Again · 2 Hard · 3 Good · 4 Easy. */
export const RATINGS = [Rating.Again, Rating.Hard, Rating.Good, Rating.Easy];

const DEFAULT_PARAMS = {
    request_retention: 0.9,
    maximum_interval: 36500,
    enable_fuzz: true,
    enable_short_term: true,
    learning_steps: ['1m', '10m'],
    relearning_steps: ['10m'],
};

/**
 * Monta a instância do FSRS com os parâmetros do usuário, validando limites.
 */
export function scheduler(params = {}) {
    const merged = { ...DEFAULT_PARAMS, ...(params || {}) };

    const retention = Number(merged.request_retention);
    const maximum = Number(merged.maximum_interval);

    return fsrs(
        generatorParameters({
            request_retention: Number.isFinite(retention) ? clamp(retention, 0.7, 0.99) : DEFAULT_PARAMS.request_retention,
            maximum_interval: Number.isFinite(maximum) ? clamp(Math.round(maximum), 1, 36500) : DEFAULT_PARAMS.maximum_interval,
            enable_fuzz: Boolean(merged.enable_fuzz),
            enable_short_term: Boolean(merged.enable_short_term),
            learning_steps: normalizeSteps(merged.learning_steps, DEFAULT_PARAMS.learning_steps),
            relearning_steps: normalizeSteps(merged.relearning_steps, DEFAULT_PARAMS.relearning_steps),
        })
    );
}

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}

function normalizeSteps(steps, fallback) {
    if (!Array.isArray(steps) || steps.length === 0) {
        return fallback;
    }

    const valid = steps
        .filter((step) => typeof step === 'string' && /^\d+(m|h|d)$/.test(step.trim()))
        .map((step) => step.trim());

    return valid.length > 0 ? valid : fallback;
}

/** Converte o payload recebido do PHP em um Card do ts-fsrs. */
export function toCard(input, now = new Date()) {
    if (!input || typeof input !== 'object') {
        return createEmptyCard(now);
    }

    const empty = createEmptyCard(now);

    const card = {
        ...empty,
        due: parseDate(input.due) || empty.due,
        stability: numberOr(input.stability, empty.stability),
        difficulty: numberOr(input.difficulty, empty.difficulty),
        elapsed_days: numberOr(input.elapsed_days, 0),
        scheduled_days: numberOr(input.scheduled_days, 0),
        reps: numberOr(input.reps, 0),
        lapses: numberOr(input.lapses, 0),
        state: normalizeState(input.state),
        last_review: parseDate(input.last_review) || undefined,
    };

    // ts-fsrs 5 controla a etapa de aprendizado no próprio cartão.
    if ('learning_steps' in empty) {
        card.learning_steps = numberOr(input.learning_step, 0);
    }

    return card;
}

/** Serializa um Card para o formato trafegado com o PHP (sempre UTC ISO). */
export function fromCard(card) {
    return {
        due: toIso(card.due),
        stability: round(card.stability),
        difficulty: round(card.difficulty),
        elapsed_days: Math.round(card.elapsed_days || 0),
        scheduled_days: Math.round(card.scheduled_days || 0),
        reps: Math.round(card.reps || 0),
        lapses: Math.round(card.lapses || 0),
        state: Number(card.state ?? State.New),
        learning_step: Math.round(card.learning_steps ?? 0),
        last_review: card.last_review ? toIso(card.last_review) : null,
    };
}

/** Serializa um ReviewLog. */
export function fromLog(log) {
    return {
        rating: Number(log.rating),
        state: Number(log.state),
        due: toIso(log.due),
        stability: round(log.stability),
        difficulty: round(log.difficulty),
        elapsed_days: Math.round(log.elapsed_days || 0),
        last_elapsed_days: Math.round(log.last_elapsed_days || 0),
        scheduled_days: Math.round(log.scheduled_days || 0),
        learning_steps: Math.round(log.learning_steps ?? 0),
        review: toIso(log.review),
    };
}

/** Converte um log recebido do banco de volta para o formato do ts-fsrs. */
export function toLog(input) {
    return {
        rating: normalizeRating(input.rating),
        state: normalizeState(input.state),
        due: parseDate(input.due) || new Date(),
        stability: numberOr(input.stability, 0),
        difficulty: numberOr(input.difficulty, 0),
        elapsed_days: numberOr(input.elapsed_days, 0),
        last_elapsed_days: numberOr(input.last_elapsed_days, 0),
        scheduled_days: numberOr(input.scheduled_days, 0),
        learning_steps: numberOr(input.learning_steps, 0),
        review: parseDate(input.review) || new Date(),
    };
}

/**
 * Pré-visualiza os quatro resultados possíveis sem aplicar nenhum deles.
 * Devolve, para cada avaliação, a data futura e um rótulo legível.
 */
export function preview(card, now, params) {
    const f = scheduler(params);
    const result = {};

    for (const rating of RATINGS) {
        const item = f.next(card, now, rating);

        result[rating] = {
            ...fromCard(item.card),
            interval_label: intervalLabel(now, item.card.due),
            interval_minutes: Math.max(0, Math.round((item.card.due - now) / 60000)),
        };
    }

    return result;
}

/** Aplica uma avaliação e devolve o novo cartão com o respectivo log. */
export function applyRating(card, rating, now, params) {
    const f = scheduler(params);
    const item = f.next(card, now, normalizeRating(rating));

    return { card: fromCard(item.card), log: fromLog(item.log) };
}

/** Desfaz a última avaliação, restaurando o estado anterior. */
export function rollback(card, log, params) {
    const f = scheduler(params);

    return fromCard(f.rollback(card, log));
}

/** Probabilidade estimada de recordar o cartão neste momento (0 a 1). */
export function retrievability(card, now, params) {
    const f = scheduler(params);
    const value = f.get_retrievability(card, now, false);

    return typeof value === 'number' ? value : Number.parseFloat(String(value)) / 100;
}

/**
 * Reconstrói o estado de um cartão reaplicando todo o histórico em ordem.
 * Usado quando parâmetros mudam ou quando o estado precisa ser recalculado.
 */
export function rebuild(history, params) {
    const f = scheduler(params);
    const entries = [...history]
        .map((entry) => ({ rating: normalizeRating(entry.rating), review: parseDate(entry.review || entry.reviewed_at) }))
        .filter((entry) => entry.review instanceof Date && !Number.isNaN(entry.review.getTime()))
        .sort((a, b) => a.review - b.review);

    let card = createEmptyCard(entries.length > 0 ? entries[0].review : new Date());
    const logs = [];

    for (const entry of entries) {
        const item = f.next(card, entry.review, entry.rating);
        card = item.card;
        logs.push(fromLog(item.log));
    }

    return { card: fromCard(card), logs };
}

/** Cartão vazio, no estado Novo. */
export function emptyCard(now = new Date()) {
    return fromCard(createEmptyCard(now));
}

// ------------------------------------------------------------------ helpers

export function normalizeRating(value) {
    const rating = Number(value);

    if (!RATINGS.includes(rating)) {
        throw new TypeError('Avaliação inválida. Use 1 (Again), 2 (Hard), 3 (Good) ou 4 (Easy).');
    }

    return rating;
}

function normalizeState(value) {
    const state = Number(value);

    return [State.New, State.Learning, State.Review, State.Relearning].includes(state) ? state : State.New;
}

export function parseDate(value) {
    if (value instanceof Date) {
        return value;
    }

    if (typeof value !== 'string' || value.trim() === '') {
        return null;
    }

    // "2026-07-29 10:00:00" (MySQL, em UTC) também precisa ser aceito.
    const normalized = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(value.trim())
        ? value.trim().replace(' ', 'T') + 'Z'
        : value.trim();

    const date = new Date(normalized);

    return Number.isNaN(date.getTime()) ? null : date;
}

function numberOr(value, fallback) {
    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : fallback;
}

function round(value) {
    const parsed = Number(value);

    return Number.isFinite(parsed) ? Math.round(parsed * 1e8) / 1e8 : 0;
}

function toIso(value) {
    const date = value instanceof Date ? value : parseDate(value);

    return (date || new Date()).toISOString().replace(/\.\d{3}Z$/, 'Z');
}

/** Rótulo em português para exibir sobre os botões de avaliação. */
export function intervalLabel(from, to) {
    const minutes = Math.max(1, Math.round((to - from) / 60000));

    if (minutes < 60) {
        return `${minutes} min`;
    }

    const hours = Math.round(minutes / 60);
    if (hours < 24) {
        return `${hours} h`;
    }

    const days = Math.round(minutes / 1440);
    if (days < 31) {
        return days === 1 ? '1 dia' : `${days} dias`;
    }

    const months = Math.round(days / 30.4);
    if (months < 12) {
        return months === 1 ? '1 mês' : `${months} meses`;
    }

    const years = Math.round((days / 365.25) * 10) / 10;

    return years === 1 ? '1 ano' : `${years} anos`;
}
