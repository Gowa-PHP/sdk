<?php

declare(strict_types=1);

use Gowa\Sdk\Config;

test('config detects whether credentials and url are properly set', function () {
    $validConfig = new Config(
        baseUrl: 'https://gowa.api.com/',
        username: 'user',
        password: 'pass'
    );

    expect($validConfig->isConfigured())->toBeTrue();
    expect($validConfig->getNormalizedBaseUrl())->toBe('https://gowa.api.com');

    $invalidConfig = new Config(
        baseUrl: '',
        username: 'user',
        password: 'pass'
    );

    expect($invalidConfig->isConfigured())->toBeFalse();
});
