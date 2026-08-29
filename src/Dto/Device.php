<?php

declare(strict_types=1);

namespace Gowa\Sdk\Dto;

final class Device
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly string $deviceId,
        public readonly string $name,
        public readonly string $status,
        public readonly ?string $phone = null,
        public readonly ?string $jid = null,
        public readonly array $raw = [],
    ) {}

    /**
     * @param array<string, mixed> $results
     */
    public static function fromResults(array $results): self
    {
        $device = (array) ($results['device'] ?? $results);

        return new self(
            deviceId: (string) ($device['id'] ?? $device['device_id'] ?? ''),
            name: (string) ($device['name'] ?? ''),
            status: strtolower((string) ($device['status'] ?? '')),
            phone: is_string($device['phone'] ?? null) && $device['phone'] !== '' ? (string) $device['phone'] : null,
            jid: is_string($device['jid'] ?? null) && $device['jid'] !== '' ? (string) $device['jid'] : null,
            raw: $results,
        );
    }

    public function isPaired(): bool
    {
        return $this->status === 'logged_in';
    }
}
