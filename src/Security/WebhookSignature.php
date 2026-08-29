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

        if (! str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expectedHash = hash_hmac('sha256', $rawPayload, $secret);
        $providedHash = substr($signatureHeader, strlen('sha256='));

        return hash_equals($expectedHash, $providedHash);
    }
}
