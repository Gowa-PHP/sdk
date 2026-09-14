<?php

declare(strict_types=1);

namespace Gowa\Sdk\Exceptions;

use Throwable;

class MediaUnavailableException extends GowaRequestException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        ?int $statusCode = null,
        ?string $gowaCode = null,
        ?string $gowaMessage = null,
    ) {
        parent::__construct(
            message: $message,
            code: $code,
            previous: $previous,
            statusCode: $statusCode,
            gowaCode: $gowaCode,
            gowaMessage: $gowaMessage,
        );
    }
}
