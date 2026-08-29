<?php

declare(strict_types=1);

namespace Gowa\Sdk;

class Config
{
    /**
     * @param list<string> $webhookEvents
     */
    public function __construct(
        public readonly string $baseUrl,
        public readonly string $username,
        public readonly string $password,
        public readonly int $timeout = 15,
        public readonly array $webhookEvents = [
            'message',
            'message.reaction',
            'message.ack',
            'message.revoked',
            'message.edited',
        ],
    ) {}

    public function isConfigured(): bool
    {
        return rtrim($this->baseUrl, '/') !== ''
            && $this->username !== ''
            && $this->password !== '';
    }

    public function getNormalizedBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }
}
