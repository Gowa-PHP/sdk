<?php

declare(strict_types=1);

namespace Gowa\Sdk\Webhook;

use ArrayAccess;
use Closure;
use Gowa\Sdk\Webhook\Dto\IncomingAck;
use Gowa\Sdk\Webhook\Dto\IncomingMessage;
use Gowa\Sdk\Webhook\Dto\IncomingReaction;

/**
 * @implements ArrayAccess<string, mixed>
 */
final class WebhookEvent implements ArrayAccess
{
    private bool $handled = false;

    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly Event $event,
        public readonly ?string $eventId,
        public readonly mixed $data,
        public readonly array $raw = [],
    ) {}

    public function isHandled(): bool
    {
        return $this->handled;
    }

    public function isMessage(): bool
    {
        return $this->event === Event::Message;
    }

    public function isAck(): bool
    {
        return $this->event === Event::MessageAck;
    }

    public function isReaction(): bool
    {
        return $this->event === Event::MessageReaction;
    }

    public function message(): ?IncomingMessage
    {
        return $this->data instanceof IncomingMessage ? $this->data : null;
    }

    public function ack(): ?IncomingAck
    {
        return $this->data instanceof IncomingAck ? $this->data : null;
    }

    public function reaction(): ?IncomingReaction
    {
        return $this->data instanceof IncomingReaction ? $this->data : null;
    }

    public function when(Event|string $event, Closure $handler): self
    {
        if ($this->handled) {
            return $this;
        }

        $target = is_string($event) ? Event::tryFromValue($event) : $event;

        if ($this->event === $target) {
            $this->handled = true;
            $handler($this->data, $this);
        }

        return $this;
    }

    public function onMessage(Closure $handler): self
    {
        return $this->when(Event::Message, $handler);
    }

    public function onAck(Closure $handler): self
    {
        return $this->when(Event::MessageAck, $handler);
    }

    public function onReaction(Closure $handler): self
    {
        return $this->when(Event::MessageReaction, $handler);
    }

    public function otherwise(Closure $handler): self
    {
        if (! $this->handled) {
            $this->handled = true;
            $handler($this->data, $this->event, $this);
        }

        return $this;
    }

    public function offsetExists(mixed $offset): bool
    {
        return in_array($offset, ['event', 'event_id', 'data', 'raw'], true);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return match ($offset) {
            'event'    => $this->event,
            'event_id' => $this->eventId,
            'data'     => $this->data,
            'raw'      => $this->raw,
            default    => null,
        };
    }

    public function offsetSet(mixed $offset, mixed $value): void {}

    public function offsetUnset(mixed $offset): void {}

    /**
     * @return array{event: Event, event_id: ?string, data: mixed, raw: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'event'    => $this->event,
            'event_id' => $this->eventId,
            'data'     => $this->data,
            'raw'      => $this->raw,
        ];
    }
}
