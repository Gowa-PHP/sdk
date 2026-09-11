<?php

declare(strict_types=1);

namespace Gowa\Sdk\Dto;

final class EventPayload
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly ?int $startTime = null,
        public readonly ?int $endTime = null,
        public readonly bool $isCanceled = false,
        public readonly ?LocationPayload $location = null,
        public readonly ?string $callLink = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $name = $data['name'] ?? $data['title'] ?? null;
        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        $start = $data['start_time'] ?? $data['startTime'] ?? null;
        $end = $data['end_time'] ?? $data['endTime'] ?? null;
        $call = $data['call_link'] ?? $data['callLink'] ?? $data['join_link'] ?? $data['joinLink'] ?? null;
        $canceled = filter_var($data['is_canceled'] ?? $data['isCanceled'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $locData = $data['location'] ?? null;
        $location = is_array($locData) ? LocationPayload::fromArray($locData) : null;

        return new self(
            name: $name,
            description: isset($data['description']) && is_string($data['description']) ? $data['description'] : null,
            startTime: $start !== null && is_numeric($start) ? (int) $start : null,
            endTime: $end !== null && is_numeric($end) ? (int) $end : null,
            isCanceled: $canceled,
            location: $location,
            callLink: is_string($call) && $call !== '' ? $call : null,
        );
    }
}
