<?php

declare(strict_types=1);

namespace Gowa\Sdk\Dto;

final class Avatar
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly string $url,
        public readonly ?string $id = null,
        public readonly array $raw = [],
    ) {}

    /**
     * @param array<string, mixed> $results
     */
    public static function fromResults(array $results): ?self
    {
        $url = (string) ($results['url'] ?? $results['avatar_url'] ?? '');

        if ($url === '') {
            return null;
        }

        $id = $results['id'] ?? null;

        return new self(
            url: $url,
            id: is_string($id) && $id !== '' ? $id : null,
            raw: $results,
        );
    }
}
