<?php

declare(strict_types=1);

namespace Gowa\Sdk\Dto;

final class SentMessage
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly string $providerMessageId = '',
        public readonly array $raw = [],
        public readonly ?string $scheduleId = null,
        public readonly ?string $scheduledAt = null,
    ) {}

    public function hasMessageId(): bool
    {
        return $this->providerMessageId !== '';
    }

    public function isScheduled(): bool
    {
        return $this->scheduleId !== null && $this->scheduleId !== '';
    }
}
