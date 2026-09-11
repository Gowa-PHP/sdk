<?php

declare(strict_types=1);

namespace Gowa\Sdk\Webhook\Dto;

use Gowa\Sdk\Dto\ContactCard;
use Gowa\Sdk\Dto\EventPayload;
use Gowa\Sdk\Dto\LiveLocationPayload;
use Gowa\Sdk\Dto\LocationPayload;
use Gowa\Sdk\Dto\OrderPayload;
use Gowa\Sdk\Dto\PollPayload;

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

    public function isLocation(): bool
    {
        return in_array($this->type, ['location', 'live_location'], true);
    }

    public function isLiveLocation(): bool
    {
        return $this->type === 'live_location';
    }

    public function location(): ?LocationPayload
    {
        $body = $this->payloadBody();
        $data = $body['location'] ?? $body['live_location'] ?? $this->raw['location'] ?? $this->raw['live_location'] ?? null;

        if (! is_array($data)) {
            return null;
        }

        return LocationPayload::fromArray($data);
    }

    public function liveLocation(): ?LiveLocationPayload
    {
        $body = $this->payloadBody();
        $data = $body['live_location'] ?? $this->raw['live_location'] ?? null;

        if (! is_array($data)) {
            return null;
        }

        return LiveLocationPayload::fromArray($data);
    }

    public function isPoll(): bool
    {
        return $this->type === 'poll';
    }

    public function poll(): ?PollPayload
    {
        $body = $this->payloadBody();
        $data = $body['poll'] ?? $this->raw['poll'] ?? null;

        if (! is_array($data)) {
            return null;
        }

        return PollPayload::fromArray($data);
    }

    public function isEvent(): bool
    {
        return $this->type === 'event';
    }

    public function event(): ?EventPayload
    {
        $body = $this->payloadBody();
        $data = $body['event_message'] ?? (isset($body['event']) && is_array($body['event']) ? $body['event'] : null)
            ?? $this->raw['event_message'] ?? (isset($this->raw['event']) && is_array($this->raw['event']) ? $this->raw['event'] : null);

        if (! is_array($data)) {
            return null;
        }

        return EventPayload::fromArray($data);
    }

    public function isOrder(): bool
    {
        return $this->type === 'order';
    }

    public function order(): ?OrderPayload
    {
        $body = $this->payloadBody();
        $data = $body['order'] ?? $this->raw['order'] ?? null;

        if (! is_array($data)) {
            return null;
        }

        return OrderPayload::fromArray($data);
    }

    public function isContact(): bool
    {
        return in_array($this->type, ['contact', 'contacts_array'], true);
    }

    public function contact(): ?ContactCard
    {
        $contacts = $this->contacts();

        return $contacts[0] ?? null;
    }

    /**
     * @return list<ContactCard>
     */
    public function contacts(): array
    {
        $body = $this->payloadBody();
        $data = $body['contact'] ?? $body['contacts_array'] ?? $this->raw['contact'] ?? $this->raw['contacts_array'] ?? null;

        if (! is_array($data)) {
            return [];
        }

        if (isset($data['displayName']) || isset($data['name']) || isset($data['vcard'])) {
            $card = ContactCard::fromArray($data);

            return $card !== null ? [$card] : [];
        }

        $cards = [];
        foreach ($data as $item) {
            if (is_array($item)) {
                $card = ContactCard::fromArray($item);
                if ($card !== null) {
                    $cards[] = $card;
                }
            }
        }

        return $cards;
    }

    public function isInteractive(): bool
    {
        return in_array($this->type, ['interactive', 'list'], true);
    }

    public function isVideoNote(): bool
    {
        return $this->type === 'video_note';
    }

    public function isMedia(): bool
    {
        return in_array($this->type, ['image', 'video', 'video_note', 'audio', 'document', 'sticker'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadBody(): array
    {
        if (isset($this->raw['payload']) && is_array($this->raw['payload'])) {
            return $this->raw['payload'];
        }

        return $this->raw;
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function extractType(array $body): string
    {
        foreach ([
            'image',
            'video',
            'video_note',
            'audio',
            'document',
            'sticker',
            'location',
            'live_location',
            'contact',
            'contacts_array',
            'poll',
            'order',
            'list',
            'interactive',
        ] as $key) {
            if (isset($body[$key])) {
                return $key;
            }
        }

        if (isset($body['event_message']) || (isset($body['event']) && is_array($body['event']))) {
            return 'event';
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

        if ($type === 'poll') {
            $poll = $body['poll'] ?? null;
            if (is_array($poll) && is_string($poll['question'] ?? null) && $poll['question'] !== '') {
                return $poll['question'];
            }
        }

        if ($type === 'event') {
            $event = $body['event_message'] ?? (isset($body['event']) && is_array($body['event']) ? $body['event'] : null);
            if (is_array($event) && is_string($event['name'] ?? $event['title'] ?? null)) {
                return $event['name'] ?? $event['title'];
            }
        }

        if ($type === 'order') {
            $order = $body['order'] ?? null;
            if (is_array($order) && is_string($order['order_title'] ?? $order['title'] ?? null)) {
                return $order['order_title'] ?? $order['title'];
            }
        }

        $media = $body[$type] ?? null;

        if (is_array($media) && is_string($media['caption'] ?? null) && $media['caption'] !== '') {
            return $media['caption'];
        }

        return null;
    }
}
