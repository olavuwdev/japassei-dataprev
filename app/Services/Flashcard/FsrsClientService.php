<?php

declare(strict_types=1);

namespace App\Services\Flashcard;

use App\Models\FlashcardSettingModel;
use Config\Flashcards as FlashcardsConfig;
use Throwable;

/**
 * Cliente HTTP do serviço interno de FSRS (Node.js + ts-fsrs).
 *
 * Regra fundamental do PRD (§13.7): nenhum intervalo é calculado aqui. Todas as
 * datas vêm do serviço. Quando ele está indisponível, a operação falha de forma
 * explícita — nunca com um agendamento aproximado.
 */
class FsrsClientService
{
    /** Estados do FSRS. */
    public const STATE_NEW        = 0;
    public const STATE_LEARNING   = 1;
    public const STATE_REVIEW     = 2;
    public const STATE_RELEARNING = 3;

    private FlashcardsConfig $config;

    public function __construct(?FlashcardsConfig $config = null)
    {
        $this->config = $config ?? config(FlashcardsConfig::class);
    }

    // ------------------------------------------------------------- Operações

    /**
     * Estado inicial de um cartão novo. Não envolve cálculo de intervalo:
     * é o estado vazio definido pelo próprio algoritmo.
     *
     * @return array<string, mixed>
     */
    public function emptyCard(?string $nowUtc = null): array
    {
        $now = $nowUtc ?? gmdate('Y-m-d H:i:s');

        return [
            'due'            => $now,
            'stability'      => 0,
            'difficulty'     => 0,
            'elapsed_days'   => 0,
            'scheduled_days' => 0,
            'reps'           => 0,
            'lapses'         => 0,
            'state'          => self::STATE_NEW,
            'learning_step'  => 0,
            'last_review'    => null,
        ];
    }

    /**
     * Pré-visualiza os quatro resultados possíveis sem aplicar nenhum deles.
     *
     * @param array<string, mixed> $card
     *
     * @return array<int, array{interval_label:string, due:string, scheduled_days:int, state:int}>
     */
    public function preview(array $card, int $userId, ?string $nowUtc = null): array
    {
        $response = $this->post('/internal/fsrs/preview', [
            'card'   => $this->toServicePayload($card),
            'now'    => $nowUtc ?? gmdate('Y-m-d\TH:i:s\Z'),
            'params' => $this->parameters($userId),
        ]);

        return $response['ratings'] ?? [];
    }

    /**
     * Aplica uma avaliação e devolve o novo estado do cartão e o log de revisão.
     *
     * @param array<string, mixed> $card
     *
     * @return array{card: array<string, mixed>, log: array<string, mixed>}
     */
    public function review(array $card, int $rating, int $userId, ?string $nowUtc = null): array
    {
        $response = $this->post('/internal/fsrs/review', [
            'card'   => $this->toServicePayload($card),
            'rating' => $rating,
            'now'    => $nowUtc ?? gmdate('Y-m-d\TH:i:s\Z'),
            'params' => $this->parameters($userId),
        ]);

        return [
            'card' => $response['card'] ?? [],
            'log'  => $response['log'] ?? [],
        ];
    }

    /**
     * Desfaz a última avaliação a partir do cartão atual e do log correspondente.
     *
     * @param array<string, mixed> $card
     * @param array<string, mixed> $log
     *
     * @return array<string, mixed> estado anterior do cartão
     */
    public function rollback(array $card, array $log, int $userId): array
    {
        $response = $this->post('/internal/fsrs/rollback', [
            'card'   => $this->toServicePayload($card),
            'log'    => $log,
            'params' => $this->parameters($userId),
        ]);

        return $response['card'] ?? [];
    }

    /**
     * Probabilidade estimada de recordar o cartão agora (0 a 1).
     *
     * @param array<string, mixed> $card
     */
    public function retrievability(array $card, int $userId, ?string $nowUtc = null): float
    {
        $response = $this->post('/internal/fsrs/retrievability', [
            'card'   => $this->toServicePayload($card),
            'now'    => $nowUtc ?? gmdate('Y-m-d\TH:i:s\Z'),
            'params' => $this->parameters($userId),
        ]);

        return (float) ($response['retrievability'] ?? 0.0);
    }

    /**
     * Reconstrói o estado de um cartão a partir do histórico completo.
     *
     * @param list<array<string, mixed>> $history
     *
     * @return array<string, mixed>
     */
    public function rebuild(array $history, int $userId): array
    {
        $response = $this->post('/internal/fsrs/rebuild', [
            'history' => array_values($history),
            'params'  => $this->parameters($userId),
        ]);

        return $response['card'] ?? [];
    }

    /**
     * Estado de saúde do serviço. Nunca lança.
     *
     * @return array{online: bool, version: ?string, message: ?string}
     */
    public function health(): array
    {
        try {
            $response = $this->request('get', '/internal/health', null, 5);

            return [
                'online'  => true,
                'version' => $response['fsrs_version'] ?? null,
                'message' => null,
            ];
        } catch (Throwable $e) {
            return [
                'online'  => false,
                'version' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    // ------------------------------------------------------------- Auxiliares

    /**
     * Parâmetros FSRS do usuário, no formato esperado pelo ts-fsrs.
     *
     * @return array<string, mixed>
     */
    public function parameters(int $userId): array
    {
        $settings = (new FlashcardSettingModel())->forUser($userId);

        $learning   = json_decode((string) ($settings['learning_steps'] ?? ''), true);
        $relearning = json_decode((string) ($settings['relearning_steps'] ?? ''), true);

        return [
            'request_retention' => (float) ($settings['request_retention'] ?? 0.9),
            'maximum_interval'  => (int) ($settings['maximum_interval'] ?? 36500),
            'enable_fuzz'       => (bool) ($settings['enable_fuzz'] ?? true),
            'enable_short_term' => (bool) ($settings['enable_short_term'] ?? true),
            'learning_steps'    => is_array($learning) && $learning !== [] ? $learning : ['1m', '10m'],
            'relearning_steps'  => is_array($relearning) && $relearning !== [] ? $relearning : ['10m'],
        ];
    }

    /**
     * Converte a linha de `study_flashcard_states` no formato do serviço.
     *
     * @param array<string, mixed> $state
     *
     * @return array<string, mixed>
     */
    public function toServicePayload(array $state): array
    {
        return [
            'due'            => $this->toIso($state['due'] ?? null) ?? gmdate('Y-m-d\TH:i:s\Z'),
            'stability'      => (float) ($state['stability'] ?? 0),
            'difficulty'     => (float) ($state['difficulty'] ?? 0),
            'elapsed_days'   => (int) ($state['elapsed_days'] ?? 0),
            'scheduled_days' => (int) ($state['scheduled_days'] ?? 0),
            'reps'           => (int) ($state['reps'] ?? 0),
            'lapses'         => (int) ($state['lapses'] ?? 0),
            'state'          => (int) ($state['state'] ?? self::STATE_NEW),
            'learning_step'  => (int) ($state['learning_step'] ?? 0),
            'last_review'    => $this->toIso($state['last_review'] ?? null),
        ];
    }

    /**
     * Converte o cartão devolvido pelo serviço em colunas do banco (UTC).
     *
     * @param array<string, mixed> $card
     *
     * @return array<string, mixed>
     */
    public function toDatabaseColumns(array $card): array
    {
        return [
            'due'            => $this->toDateTime($card['due'] ?? null) ?? gmdate('Y-m-d H:i:s'),
            'stability'      => (float) ($card['stability'] ?? 0),
            'difficulty'     => (float) ($card['difficulty'] ?? 0),
            'elapsed_days'   => (int) ($card['elapsed_days'] ?? 0),
            'scheduled_days' => (int) ($card['scheduled_days'] ?? 0),
            'reps'           => (int) ($card['reps'] ?? 0),
            'lapses'         => (int) ($card['lapses'] ?? 0),
            'state'          => (int) ($card['state'] ?? self::STATE_NEW),
            'learning_step'  => (int) ($card['learning_step'] ?? 0),
            'last_review'    => $this->toDateTime($card['last_review'] ?? null),
        ];
    }

    private function toIso(?string $value): ?string
    {
        if ($value === null || $value === '' || str_starts_with($value, '0000')) {
            return null;
        }

        $timestamp = strtotime($value . (str_contains($value, 'T') || str_contains($value, '+') ? '' : ' UTC'));

        return $timestamp === false ? null : gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    }

    private function toDateTime(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : gmdate('Y-m-d H:i:s', $timestamp);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        return $this->request('post', $path, $payload, $this->config->fsrsTimeout);
    }

    /**
     * @param array<string, mixed>|null $payload
     *
     * @return array<string, mixed>
     *
     * @throws FsrsUnavailableException
     */
    private function request(string $method, string $path, ?array $payload, int $timeout): array
    {
        if ($this->config->fsrsUrl === '') {
            throw new FsrsUnavailableException('O serviço de agendamento (FSRS) não está configurado.');
        }

        $options = [
            'timeout'     => $timeout,
            'http_errors' => false,
            'headers'     => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ];

        if ($this->config->fsrsToken !== '') {
            $options['headers']['X-Internal-Token'] = $this->config->fsrsToken;
        }

        if ($payload !== null) {
            $options['body'] = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }

        try {
            $client   = service('curlrequest', ['baseURI' => $this->config->fsrsUrl . '/'], null, null, false);
            $response = $client->request(strtoupper($method), ltrim($path, '/'), $options);
        } catch (Throwable $e) {
            log_message('error', 'FSRS indisponível: {msg}', ['msg' => $e->getMessage()]);

            throw new FsrsUnavailableException('Não foi possível contatar o serviço de agendamento.');
        }

        $status = $response->getStatusCode();
        $body   = json_decode((string) $response->getBody(), true);

        if ($status >= 400 || ! is_array($body)) {
            $message = is_array($body) ? (string) ($body['message'] ?? 'Erro no serviço FSRS.') : 'Resposta inválida do serviço FSRS.';

            log_message('error', 'FSRS respondeu {status}: {msg}', ['status' => $status, 'msg' => $message]);

            throw new FsrsUnavailableException($message);
        }

        return $body;
    }
}
