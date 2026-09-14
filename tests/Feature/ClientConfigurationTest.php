<?php

declare(strict_types=1);

use Gowa\Sdk\Config;
use Gowa\Sdk\GowaClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

test('GowaClient with handler preserves basic auth, base_uri, timeout, and accept header from Config', function () {
    $mockHandler = new MockHandler([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [
                'id'     => 'dev-1',
                'name'   => 'Test',
                'status' => 'connected',
            ],
        ])),
    ]);

    $config = new Config(
        baseUrl: 'https://whatsapp.company.com/api',
        username: 'gowa_user',
        password: 'gowa_secret_pass',
        timeout: 45,
    );

    $client = new GowaClient($config, handler: $mockHandler);
    $device = $client->device('dev-1');

    expect($device)->not->toBeNull();

    $lastRequest = $mockHandler->getLastRequest();
    $lastOptions = $mockHandler->getLastOptions();

    // 1. Basic auth
    $expectedAuth = 'Basic ' . base64_encode('gowa_user:gowa_secret_pass');
    expect($lastRequest->getHeaderLine('Authorization'))->toBe($expectedAuth);

    // 2. Base URI
    expect((string) $lastRequest->getUri())->toBe('https://whatsapp.company.com/api/devices/dev-1');

    // 3. Timeout from config
    expect($lastOptions['timeout'] ?? null)->toBe(45);

    // 4. Accept header
    expect($lastRequest->getHeaderLine('Accept'))->toBe('application/json');
});

test('GowaClient maintains custom GuzzleClient injection for backwards compatibility', function () {
    $mockHandler = new MockHandler([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [
                'id'     => 'dev-custom',
                'name'   => 'Custom Client',
                'status' => 'connected',
            ],
        ])),
    ]);

    $customGuzzle = new GuzzleClient([
        'handler'  => $mockHandler,
        'base_uri' => 'https://custom.example.com/',
        'headers'  => ['X-Custom-Header' => 'CustomValue'],
    ]);

    $config = new Config(
        baseUrl: 'https://fallback.example.com',
        username: 'user',
        password: 'pass',
    );

    $client = new GowaClient($config, client: $customGuzzle);
    $device = $client->device('dev-custom');

    expect($device)->not->toBeNull();

    $lastRequest = $mockHandler->getLastRequest();
    expect($lastRequest->getHeaderLine('X-Custom-Header'))->toBe('CustomValue');
});

test('GowaClient wraps raw callable handler with HandlerStack', function () {
    $called = false;
    $rawHandler = function ($request, $options) use (&$called) {
        $called = true;
        return new \GuzzleHttp\Promise\FulfilledPromise(
            new Response(200, [], json_encode(['code' => 'SUCCESS', 'results' => ['id' => 'dev-raw']])),
        );
    };

    $config = new Config(
        baseUrl: 'https://gowa.example.com',
        username: 'admin',
        password: 'secretpassword',
    );

    $client = new GowaClient($config, handler: $rawHandler);
    $device = $client->device('dev-raw');

    expect($called)->toBeTrue();
    expect($device)->not->toBeNull();
    expect($device->deviceId)->toBe('dev-raw');
});
