<?php

declare(strict_types=1);

namespace Gowa\Sdk\Security;

final class WebhookSignature
{
    public static function verify(string $rawPayload, string $signatureHeader, string $secret): bool
    {
        if ($secret === '' || $signatureHeader === '') {
            return false;
        }

        $providedHash = str_starts_with($signatureHeader, 'sha256=')
            ? substr($signatureHeader, 7)
            : $signatureHeader;

        $expectedHash = hash_hmac('sha256', $rawPayload, $secret);

        return hash_equals($expectedHash, $providedHash);
    }
}
