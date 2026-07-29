/**
 * Serviço interno de agendamento FSRS.
 *
 * Sem dependências de servidor (apenas o módulo http nativo) para simplificar a
 * implantação em hospedagem Node compartilhada. NÃO deve ficar exposto
 * diretamente à internet: publique apenas em rede interna ou proteja com o
 * token compartilhado em FSRS_SERVICE_TOKEN.
 */

import { readFileSync } from 'node:fs';
import http from 'node:http';
import { createRequire } from 'node:module';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

import {
    applyRating,
    emptyCard,
    parseDate,
    preview,
    rebuild,
    retrievability,
    rollback,
    toCard,
    toLog,
} from './src/fsrs.js';

const FSRS_VERSION = (() => {
    // O pacote não expõe package.json no mapa de exports; resolvemos o arquivo
    // a partir do caminho do módulo principal.
    try {
        const require = createRequire(import.meta.url);
        const entry = require.resolve('ts-fsrs');
        let dir = dirname(entry);

        for (let depth = 0; depth < 5; depth++) {
            try {
                const pkg = JSON.parse(readFileSync(join(dir, 'package.json'), 'utf8'));

                if (pkg.name === 'ts-fsrs') {
                    return pkg.version;
                }
            } catch {
                // segue subindo
            }

            dir = dirname(dir);
        }
    } catch {
        // ignora: a versão é informativa
    }

    try {
        const local = dirname(fileURLToPath(import.meta.url));

        return JSON.parse(readFileSync(join(local, 'node_modules/ts-fsrs/package.json'), 'utf8')).version;
    } catch {
        return 'desconhecida';
    }
})();

const PORT = Number(process.env.PORT || process.env.FSRS_PORT || 3100);
const HOST = process.env.HOST || '127.0.0.1';
const TOKEN = process.env.FSRS_SERVICE_TOKEN || '';
const MAX_BODY_BYTES = 2 * 1024 * 1024;

const routes = {
    'POST /internal/fsrs/cards': handleCreateCard,
    'POST /internal/fsrs/preview': handlePreview,
    'POST /internal/fsrs/review': handleReview,
    'POST /internal/fsrs/rollback': handleRollback,
    'POST /internal/fsrs/retrievability': handleRetrievability,
    'POST /internal/fsrs/rebuild': handleRebuild,
    'GET /internal/health': handleHealth,
};

const server = http.createServer(async (req, res) => {
    const url = new URL(req.url, `http://${req.headers.host || 'localhost'}`);
    const key = `${req.method} ${url.pathname}`;
    const handler = routes[key];

    if (!handler) {
        return send(res, 404, { message: 'Rota não encontrada.' });
    }

    if (key !== 'GET /internal/health' && !authorized(req)) {
        return send(res, 401, { message: 'Token interno ausente ou inválido.' });
    }

    try {
        const body = req.method === 'POST' ? await readJson(req) : {};
        const payload = await handler(body);

        send(res, 200, payload);
    } catch (error) {
        const status = error instanceof TypeError || error.status === 422 ? 422 : 500;

        if (status === 500) {
            console.error('[fsrs] erro inesperado:', error);
        }

        send(res, status, { message: error.message || 'Erro ao processar a requisição.' });
    }
});

server.listen(PORT, HOST, () => {
    console.log(`[fsrs] ouvindo em http://${HOST}:${PORT} (ts-fsrs ${FSRS_VERSION})`);

    if (TOKEN === '') {
        console.warn('[fsrs] FSRS_SERVICE_TOKEN não definido: o serviço aceitará qualquer chamada. Use apenas em rede interna.');
    }
});

// ----------------------------------------------------------------- handlers

function handleCreateCard(body) {
    return { card: emptyCard(parseDate(body.now) || new Date()) };
}

function handlePreview(body) {
    const now = parseDate(body.now) || new Date();

    return { ratings: preview(toCard(body.card, now), now, body.params) };
}

function handleReview(body) {
    const now = parseDate(body.now) || new Date();

    return applyRating(toCard(body.card, now), body.rating, now, body.params);
}

function handleRollback(body) {
    if (!body.log || typeof body.log !== 'object') {
        throw new TypeError('O log da revisão anterior é obrigatório para desfazer.');
    }

    return { card: rollback(toCard(body.card), toLog(body.log), body.params) };
}

function handleRetrievability(body) {
    const now = parseDate(body.now) || new Date();

    return { retrievability: retrievability(toCard(body.card, now), now, body.params) };
}

function handleRebuild(body) {
    if (!Array.isArray(body.history)) {
        throw new TypeError('O histórico deve ser uma lista de revisões.');
    }

    return rebuild(body.history, body.params);
}

function handleHealth() {
    return {
        status: 'ok',
        fsrs_version: FSRS_VERSION,
        node_version: process.version,
        uptime_seconds: Math.round(process.uptime()),
    };
}

// ------------------------------------------------------------------ helpers

function authorized(req) {
    if (TOKEN === '') {
        return true;
    }

    const received = String(req.headers['x-internal-token'] || '');

    if (received.length !== TOKEN.length) {
        return false;
    }

    // Comparação de tempo constante.
    let diff = 0;
    for (let i = 0; i < TOKEN.length; i++) {
        diff |= TOKEN.charCodeAt(i) ^ received.charCodeAt(i);
    }

    return diff === 0;
}

function readJson(req) {
    return new Promise((resolve, reject) => {
        const chunks = [];
        let size = 0;

        req.on('data', (chunk) => {
            size += chunk.length;

            if (size > MAX_BODY_BYTES) {
                reject(Object.assign(new Error('Corpo da requisição excede o limite.'), { status: 422 }));
                req.destroy();
                return;
            }

            chunks.push(chunk);
        });

        req.on('error', reject);

        req.on('end', () => {
            const raw = Buffer.concat(chunks).toString('utf8').trim();

            if (raw === '') {
                return resolve({});
            }

            try {
                resolve(JSON.parse(raw));
            } catch {
                reject(Object.assign(new TypeError('JSON inválido.'), { status: 422 }));
            }
        });
    });
}

function send(res, status, payload) {
    const body = JSON.stringify(payload);

    res.writeHead(status, {
        'Content-Type': 'application/json; charset=utf-8',
        'Content-Length': Buffer.byteLength(body),
        'Cache-Control': 'no-store',
    });

    res.end(body);
}

export default server;
