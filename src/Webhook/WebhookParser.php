<?php

declare(strict_types=1);

namespace Gowa\Sdk\Webhook;

use Gowa\Sdk\Webhook\Dto\IncomingAck;
use Gowa\Sdk\Webhook\Dto\IncomingMessage;
use Gowa\Sdk\Webhook\Dto\IncomingReaction;

final class WebhookParser
{
    /**
     * Parse raw json payload or array into structured event object
     *
     * @param string|array<string, mixed> $rawPayload
     * @return array{event: Event, event_id: ?string, data: mixed, raw: array<string, mixed>}
     */
    public static function parse(string|array $rawPayload): array
    {
        $payload = is_string($rawPayload)
            ? (json_decode($rawPayload, true) ?? [])
            : $rawPayload;

        if (! is_array($payload)) {
            $payload = [];
        }

        $eventString = (string) ($payload['event'] ?? '');
        $event = Event::tryFromValue($eventString);
        $eventId = self::extractEventId($payload, $eventString);

        $parsedData = match ($event) {
            Event::Message         => IncomingMessage::fromPayload($payload),
            Event::MessageAck      => IncomingAck::fromPayload($payload),
            Event::MessageReaction => IncomingReaction::fromPayload($payload),
            default                => $payload['payload'] ?? $payload,
        };

        return [
            'event'    => $event,
            'event_id' => $eventId,
            'data'     => $parsedData,
            'raw'      => $payload,
        ];
    }

    /**
     * Extract a stable deduplication ID for the webhook event
     *
     * @param array<string, mixed> $payload
     */
    public static function extractEventId(array $payload, string $eventString): ?string
    {
        $event = (string) ($payload['event'] ?? $eventString);
        $body = (array) ($payload['payload'] ?? []);

        if ($event === 'message.ack') {
            $ids = $body['ids'] ?? null;
            $receipt = (string) ($body['receipt_type'] ?? '');

            if (! is_array($ids) || $ids === [] || $receipt === '') {
                return null;
            }

            return 'ack:' . implode(',', array_map('strval', $ids)) . ":{$receipt}";
        }

        $id = $body['id'] ?? null;

        if (! is_string($id) || $id === '') {
            return null;
        }

        return "{$event}:{$id}";
    }
}
