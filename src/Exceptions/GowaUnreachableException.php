<?php

declare(strict_types=1);

namespace Gowa\Sdk\Exceptions;

use Throwable;

class GowaUnreachableException extends GowaRequestException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            code: $code,
            previous: $previous,
            statusCode: null,
            gowaCode: null,
            gowaMessage: null,
        );
    }
}
