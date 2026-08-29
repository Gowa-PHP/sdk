<?php

declare(strict_types=1);

namespace Gowa\Sdk\Dto;

final class LocationPayload
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
    ) {}
}
