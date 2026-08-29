<?php

declare(strict_types=1);

namespace Gowa\Sdk\Dto;

enum MediaType: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';
}
