<?php

declare(strict_types=1);

namespace Gowa\Sdk\Dto;

final class LocationPayload
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $lat = $data['degreesLatitude'] ?? $data['latitude'] ?? $data['lat'] ?? null;
        $lng = $data['degreesLongitude'] ?? $data['longitude'] ?? $data['lng'] ?? $data['lon'] ?? null;

        if ($lat === null || $lng === null || ! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        return new self(
            latitude: (float) $lat,
            longitude: (float) $lng,
        );
    }
}
