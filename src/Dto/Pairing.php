<?php

declare(strict_types=1);

namespace Gowa\Sdk\Dto;

final class Pairing
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly ?string $qrLink = null,
        public readonly ?string $pairCode = null,
        public readonly array $raw = [],
    ) {}

    /**
     * @param array<string, mixed> $results
     */
    public static function fromQr(array $results): self
    {
        $qr = (string) ($results['qr_link'] ?? $results['qr'] ?? '');

        return new self(
            qrLink: $qr !== '' ? $qr : null,
            raw: $results,
        );
    }

    /**
     * @param array<string, mixed> $results
     */
    public static function fromCode(array $results): self
    {
        $code = (string) ($results['pair_code'] ?? $results['code'] ?? '');

        return new self(
            pairCode: $code !== '' ? $code : null,
            raw: $results,
        );
    }
}
