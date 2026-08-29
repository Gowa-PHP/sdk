<?php

declare(strict_types=1);

namespace Gowa\Sdk\Dto;

final class ContactCard
{
    /**
     * @param list<array{phone: string}> $phones
     */
    public function __construct(
        public readonly string $name,
        public readonly array $phones = [],
    ) {}
}
