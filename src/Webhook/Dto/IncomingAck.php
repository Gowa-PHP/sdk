<?php

declare(strict_types=1);

namespace Gowa\Sdk\Webhook\Dto;

final class IncomingAck
{
    /**
     * @param list<string> $messageIds
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly array $messageIds,
        public readonly string $receiptType,
        public readonly ?string $chatId = null,
        public readonly array $raw = [],
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): ?self
    {
        $body = (array) ($payload['payload'] ?? $payload);
        $ids = $body['ids'] ?? null;
        $receipt = (string) ($body['receipt_type'] ?? $body['receipt'] ?? '');

        if (! is_array($ids) || $ids === [] || $receipt === '') {
            return null;
        }

        /** @var list<string> $cleanIds */
        $cleanIds = array_values(array_filter(array_map('strval', $ids)));

        return new self(
            messageIds: $cleanIds,
            receiptType: strtolower($receipt),
            chatId: is_string($body['chat_id'] ?? null) ? (string) $body['chat_id'] : null,
            raw: $payload,
        );
    }

    public function isDelivered(): bool
    {
        return in_array($this->receiptType, ['delivered', 'delivery'], true);
    }

    public function isRead(): bool
    {
        return in_array($this->receiptType, ['read', 'read-self'], true);
    }
}
