<?php

declare(strict_types=1);

namespace Gowa\Sdk\Dto;

final class LiveLocationPayload
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly ?int $accuracyInMeters = null,
        public readonly ?float $speedInMps = null,
        public readonly ?int $degreesClockwiseFromMagneticNorth = null,
        public readonly ?string $caption = null,
        public readonly ?int $sequenceNumber = null,
        public readonly ?int $timeOffset = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $lat = $data['degreesLatitude'] ?? $data['latitude'] ?? $data['lat'] ?? null;
        $lng = $data['degreesLongitude'] ?? $data['longitude'] ?? $data['lng'] ?? null;

        if ($lat === null || $lng === null || ! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        $accuracy = $data['accuracyInMeters'] ?? $data['accuracy_in_meters'] ?? null;
        $speed = $data['speedInMps'] ?? $data['speed_in_mps'] ?? null;
        $bearing = $data['degreesClockwiseFromMagneticNorth'] ?? $data['degrees_clockwise_from_magnetic_north'] ?? null;
        $caption = $data['caption'] ?? null;
        $sequence = $data['sequenceNumber'] ?? $data['sequence_number'] ?? null;
        $timeOffset = $data['timeOffset'] ?? $data['time_offset'] ?? null;

        return new self(
            latitude: (float) $lat,
            longitude: (float) $lng,
            accuracyInMeters: $accuracy !== null && is_numeric($accuracy) ? (int) $accuracy : null,
            speedInMps: $speed !== null && is_numeric($speed) ? (float) $speed : null,
            degreesClockwiseFromMagneticNorth: $bearing !== null && is_numeric($bearing) ? (int) $bearing : null,
            caption: is_string($caption) && $caption !== '' ? $caption : null,
            sequenceNumber: $sequence !== null && is_numeric($sequence) ? (int) $sequence : null,
            timeOffset: $timeOffset !== null && is_numeric($timeOffset) ? (int) $timeOffset : null,
        );
    }

    public function toLocationPayload(): LocationPayload
    {
        return new LocationPayload(
            latitude: $this->latitude,
            longitude: $this->longitude,
        );
    }
}
