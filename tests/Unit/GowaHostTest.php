<?php

declare(strict_types=1);

use Gowa\Sdk\Exceptions\GowaSecurityException;
use Gowa\Sdk\Security\GowaHost;

test('gowa host allows relative paths and matching server host', function () {
    expect(fn() => GowaHost::assertBelongsToServer('/relative/path', 'https://gowa.example.com'))->not->toThrow(Exception::class);
    expect(fn() => GowaHost::assertBelongsToServer('https://gowa.example.com/file.png', 'https://gowa.example.com'))->not->toThrow(Exception::class);
});

test('gowa host throws security exception for external hosts', function () {
    expect(fn() => GowaHost::assertBelongsToServer('https://evil-server.com/malicious', 'https://gowa.example.com'))
        ->toThrow(GowaSecurityException::class);
});
