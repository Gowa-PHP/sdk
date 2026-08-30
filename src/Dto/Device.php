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

        $phone = null;
        if (is_string($device['phone'] ?? null) && $device['phone'] !== '') {
            $phone = (string) $device['phone'];
        } elseif (is_string($device['jid'] ?? null) && str_contains((string) $device['jid'], '@')) {
            $phone = explode('@', (string) $device['jid'])[0];
        }

        return new self(
            deviceId: (string) ($device['id'] ?? $device['device_id'] ?? ''),
            name: (string) ($device['display_name'] ?? $device['name'] ?? ''),
            status: strtolower((string) ($device['state'] ?? $device['status'] ?? '')),
            phone: $phone,
            jid: is_string($device['jid'] ?? null) && $device['jid'] !== '' ? (string) $device['jid'] : null,
            raw: $results,
        );
    }

    public function isPaired(): bool
    {
        return in_array($this->status, ['logged_in', 'open', 'connected', 'authenticated', 'paired'], true);
    }
}
