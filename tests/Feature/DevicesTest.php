<?php

declare(strict_types=1);

use Gowa\Sdk\Config;
use Gowa\Sdk\Dto\Device;
use Gowa\Sdk\Dto\Pairing;
use Gowa\Sdk\Exceptions\GowaRequestException;
use Gowa\Sdk\Exceptions\GowaUnreachableException;
use Gowa\Sdk\GowaClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

test('createDevice sends device and webhook configuration', function () {
    $client = createMockGowaClient([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [
                'id'     => 'device-uuid-1',
                'name'   => 'Vendas',
                'status' => 'disconnected',
            ],
        ])),
    ]);

    $device = $client->createDevice(
        deviceId: 'device-uuid-1',
        webhookUrl: 'https://app.com/webhooks/gowa/device-uuid-1',
        webhookSecret: 'sec_123',
        events: ['message', 'message.ack'],
    );

    expect($device)->toBeInstanceOf(Device::class);
    expect($device->deviceId)->toBe('device-uuid-1');
    expect($device->isPaired())->toBeFalse();
});

test('startQrPairing returns pairing object with qr link', function () {
    $client = createMockGowaClient([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [
                'qr_link' => 'https://gowa.example.com/qr/device-uuid-1',
            ],
        ])),
    ]);

    $pairing = $client->startQrPairing('device-uuid-1');

    expect($pairing)->toBeInstanceOf(Pairing::class);
    expect($pairing->qrLink)->toBe('https://gowa.example.com/qr/device-uuid-1');
});

test('startCodePairing returns pairing code', function () {
    $client = createMockGowaClient([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [
                'pair_code' => 'K8J9-2L11',
            ],
        ])),
    ]);

    $pairing = $client->startCodePairing('device-uuid-1', '5511999998888');

    expect($pairing->pairCode)->toBe('K8J9-2L11');
});

test('updateWebhook sends X-Device-Id as header and not as query parameter', function () {
    $mockHandler = new MockHandler([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [
                'device_id'   => 'device-uuid-1',
                'webhook_url' => 'https://app.com/webhooks/gowa/device-uuid-1',
            ],
        ])),
    ]);

    $config = new Config(
        baseUrl: 'https://gowa.example.com',
        username: 'admin',
        password: 'secretpassword',
    );
    $client = new GowaClient($config, handler: $mockHandler);

    $results = $client->updateWebhook(
        deviceId: 'device-uuid-1',
        webhookUrl: 'https://app.com/webhooks/gowa/device-uuid-1',
        webhookSecret: 'sec_123',
        events: ['message', 'message.ack'],
    );

    expect($results)->toBeArray();
    expect($results['device_id'])->toBe('device-uuid-1');

    $lastRequest = $mockHandler->getLastRequest();
    expect($lastRequest->getHeaderLine('X-Device-Id'))->toBe('device-uuid-1');
    expect($lastRequest->getUri()->getQuery())->not->toContain('X-Device-Id');
});

test('device queries device state and returns Device DTO', function () {
    $client = createMockGowaClient([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [
                'id'     => 'device-uuid-1',
                'name'   => 'Financeiro',
                'status' => 'connected',
            ],
        ])),
    ]);

    $device = $client->device('device-uuid-1');

    expect($device)->toBeInstanceOf(Device::class);
    expect($device->deviceId)->toBe('device-uuid-1');
});

test('device returns null for 404 response by status code', function () {
    $client = createMockGowaClient([
        new Response(404, [], json_encode([
            'code'    => 'NOT_FOUND',
            'message' => 'device does not exist',
        ])),
    ]);

    $device = $client->device('unknown-device');

    expect($device)->toBeNull();
});

test('device throws GowaRequestException on 500 server error', function () {
    $client = createMockGowaClient([
        new Response(500, [], json_encode([
            'code'    => 'INTERNAL_SERVER_ERROR',
            'message' => 'database down',
        ])),
    ]);

    expect(fn() => $client->device('device-uuid-1'))
        ->toThrow(GowaRequestException::class, 'database down');
});

test('device throws GowaUnreachableException on network failure', function () {
    $mockHandler = new MockHandler([
        new ConnectException('Connection refused', new Request('GET', 'devices/device-uuid-1')),
    ]);

    $config = new Config(
        baseUrl: 'https://gowa.example.com',
        username: 'admin',
        password: 'secretpassword',
    );
    $client = new GowaClient($config, handler: $mockHandler);

    expect(fn() => $client->device('device-uuid-1'))
        ->toThrow(GowaUnreachableException::class, 'Connection refused');
});
