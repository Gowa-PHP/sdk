<?php

declare(strict_types=1);

namespace Gowa\Sdk\Exceptions;

use Throwable;

class GowaRequestException extends GowaException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?int $statusCode = null,
        public readonly ?string $gowaCode = null,
        public readonly ?string $gowaMessage = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getGowaCode(): ?string
    {
        return $this->gowaCode;
    }

    public function getGowaMessage(): ?string
    {
        return $this->gowaMessage;
    }
}
