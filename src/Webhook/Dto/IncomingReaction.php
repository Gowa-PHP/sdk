<?php

declare(strict_types=1);

namespace Gowa\Sdk\Webhook\Dto;

final class IncomingReaction
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly string $chatId,
        public readonly string $targetMessageId,
        public readonly ?string $emoji,
        public readonly bool $isEcho = false,
        public readonly array $raw = [],
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): ?self
    {
        $body = (array) ($payload['payload'] ?? $payload);
        $id = (string) ($body['id'] ?? '');
        $chat = (string) ($body['chat_id'] ?? '');
        $target = (string) ($body['reacted_message_id'] ?? '');

        if ($id === '' || $chat === '' || $target === '') {
            return null;
        }

        $emoji = $body['reaction'] ?? null;

        return new self(
            id: $id,
            chatId: $chat,
            targetMessageId: $target,
            emoji: is_string($emoji) && $emoji !== '' ? $emoji : null,
            isEcho: (bool) ($body['is_from_me'] ?? false),
            raw: $payload,
        );
    }
}
