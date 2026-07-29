<?php

declare(strict_types=1);

namespace App\Services\Flashcard;

use App\Models\FlashcardAuditLogModel;
use Throwable;

/**
 * Registro dos eventos de auditoria do módulo (seção 26 do PRD).
 * Nunca deve interromper a operação principal.
 */
class FlashcardAuditService
{
    public const CARD_CREATED     = 'card.created';
    public const CARD_UPDATED     = 'card.updated';
    public const CARD_DELETED     = 'card.deleted';
    public const CARD_SUSPENDED   = 'card.suspended';
    public const CARD_RESUMED     = 'card.resumed';
    public const CARD_REVIEWED    = 'card.reviewed';
    public const REVIEW_UNDONE    = 'review.undone';
    public const AI_GENERATED     = 'ai.generated';
    public const AI_APPROVED      = 'ai.approved';
    public const AI_FAILED        = 'ai.failed';
    public const SETTINGS_UPDATED = 'settings.updated';
    public const TOKEN_CREATED    = 'token.created';
    public const TOKEN_REVOKED    = 'token.revoked';
    public const IMPORT_RECEIVED  = 'import.received';

    /**
     * @param array<string, mixed> $context
     */
    public function log(int $userId, string $event, ?string $entityType = null, ?int $entityId = null, array $context = []): void
    {
        try {
            (new FlashcardAuditLogModel())->insert([
                'user_id'     => $userId,
                'event'       => $event,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'context'     => $context === [] ? null : json_encode($context, JSON_UNESCAPED_UNICODE),
                'ip_address'  => $this->clientIp(),
            ]);
        } catch (Throwable $e) {
            log_message('error', 'Falha ao registrar auditoria de flashcards: {msg}', ['msg' => $e->getMessage()]);
        }
    }

    private function clientIp(): ?string
    {
        if (! is_cli()) {
            return service('request')->getIPAddress();
        }

        return null;
    }
}
