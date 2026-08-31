<?php

declare(strict_types=1);

use Gowa\Sdk\Security\WebhookSignature;

test('validates correct HMAC SHA-256 webhook signature with or without sha256= prefix', function () {
    $payload = '{"event":"message","payload":{}}';
    $secret = 'my_super_secret';
    $hash = hash_hmac('sha256', $payload, $secret);

    expect(WebhookSignature::verify($payload, "sha256={$hash}", $secret))->toBeTrue();
    expect(WebhookSignature::verify($payload, $hash, $secret))->toBeTrue();
});

test('rejects invalid or missing webhook signature', function () {
    $payload = '{"event":"message","payload":{}}';
    $secret = 'my_super_secret';

    expect(WebhookSignature::verify($payload, 'sha256=invalid_hash', $secret))->toBeFalse();
    expect(WebhookSignature::verify($payload, 'invalid_hash', $secret))->toBeFalse();
    expect(WebhookSignature::verify($payload, '', $secret))->toBeFalse();
    expect(WebhookSignature::verify($payload, 'sha256=xxx', ''))->toBeFalse();
});
