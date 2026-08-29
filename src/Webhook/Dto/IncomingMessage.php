<?php

declare(strict_types=1);

namespace Gowa\Sdk\Webhook\Dto;

final class IncomingMessage
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly string $chatId,
        public readonly string $phone,
        public readonly ?string $senderName,
        public readonly bool $isEcho,
        public readonly string $type,
        public readonly ?string $body,
        public readonly ?string $quotedMessageId = null,
        public readonly ?string $timestamp = null,
        public readonly bool $isGroup = false,
        public readonly array $raw = [],
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): ?self
    {
        $body = (array) ($payload['payload'] ?? $payload);
        $id = (string) ($body['id'] ?? '');
        $chat = (string) ($body['chat_id'] ?? $body['from'] ?? '');

        if ($id === '' || $chat === '') {
            return null;
        }

        $atPos = strpos($chat, '@');
        $phone = $atPos === false ? $chat : substr($chat, 0, $atPos);

        $senderName = $body['sender_display_name'] ?? $body['from_name'] ?? null;
        $type = self::extractType($body);
        $text = self::extractBody($body, $type);

        return new self(
            id: $id,
            chatId: $chat,
            phone: $phone,
            senderName: is_string($senderName) && $senderName !== '' ? $senderName : null,
            isEcho: (bool) ($body['is_from_me'] ?? false),
            type: $type,
            body: $text,
            quotedMessageId: is_string($body['replied_to_id'] ?? null) ? (string) $body['replied_to_id'] : null,
            timestamp: is_string($body['timestamp'] ?? null) ? (string) $body['timestamp'] : null,
            isGroup: str_ends_with($chat, '@g.us'),
            raw: $payload,
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function extractType(array $body): string
    {
        foreach (['image', 'video', 'audio', 'document', 'sticker', 'location', 'contact'] as $key) {
            if (isset($body[$key])) {
                return $key;
            }
        }

        return 'text';
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function extractBody(array $body, string $type): ?string
    {
        $text = $body['body'] ?? null;

        if (is_string($text) && $text !== '') {
            return $text;
        }

        if ($type === 'text') {
            return null;
        }

        $media = $body[$type] ?? null;

        if (is_array($media) && is_string($media['caption'] ?? null) && $media['caption'] !== '') {
            return $media['caption'];
        }

        return null;
    }
}
