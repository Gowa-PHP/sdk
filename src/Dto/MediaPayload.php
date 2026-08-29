<?php

declare(strict_types=1);

namespace Gowa\Sdk\Dto;

final class MediaPayload
{
    public function __construct(
        public readonly MediaType $type,
        public readonly ?MediaUpload $upload = null,
        public readonly ?string $caption = null,
        public readonly bool $voice = false,
    ) {}
}
