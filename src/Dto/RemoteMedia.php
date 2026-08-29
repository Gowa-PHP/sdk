<?php

declare(strict_types=1);

namespace Gowa\Sdk\Dto;

final class RemoteMedia
{
    public function __construct(
        public readonly string $url,
        public readonly ?string $mimeType = null,
        public readonly int $sizeBytes = 0,
        public readonly ?string $filename = null,
    ) {}
}
