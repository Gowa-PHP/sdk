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

test('device methods send raw deviceId in headers and body while encoding URL path segments', function () {
    $mockHandler = new MockHandler([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => ['id' => 'loja 1', 'name' => 'Loja 1'],
        ])),
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => ['device_id' => 'loja 1'],
        ])),
    ]);

    $config = new Config(
        baseUrl: 'https://gowa.example.com',
        username: 'admin',
        password: 'secretpassword',
    );
    $client = new GowaClient($config, handler: $mockHandler);

    $client->createDevice(' loja 1 ', 'https://app.com/wh', 'sec', ['message']);
    $createReq = $mockHandler->getLastRequest();
    $createBody = json_decode((string) $createReq->getBody(), true);
    expect($createBody['device_id'])->toBe('loja 1');

    $client->updateWebhook(' loja 1 ', 'https://app.com/wh2');
    $updateReq = $mockHandler->getLastRequest();
    expect($updateReq->getHeaderLine('X-Device-Id'))->toBe('loja 1')
        ->and($updateReq->getUri()->getPath())->toBe('/devices/loja%201/webhook');
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

test('devices returns list of Device objects and listDevices is alias', function () {
    $client = createMockGowaClient([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [
                [
                    'id'           => 'dev-1',
                    'display_name' => 'Device 1',
                    'state'        => 'logged_in',
                    'phone_number' => '5511999998888',
                ],
                [
                    'id'           => 'dev-2',
                    'display_name' => 'Device 2',
                    'state'        => 'disconnected',
                    'phone_number' => '5511888887777',
                ],
            ],
        ])),
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [
                [
                    'id'           => 'dev-1',
                    'display_name' => 'Device 1',
                    'state'        => 'logged_in',
                ],
            ],
        ])),
    ]);

    $devices = $client->devices();
    expect($devices)->toHaveCount(2)
        ->and($devices[0])->toBeInstanceOf(Device::class)
        ->and($devices[0]->deviceId)->toBe('dev-1')
        ->and($devices[0]->isPaired())->toBeTrue()
        ->and($devices[1]->deviceId)->toBe('dev-2')
        ->and($devices[1]->isPaired())->toBeFalse();

    $aliasDevices = $client->listDevices();
    expect($aliasDevices)->toHaveCount(1);
});

test('deleteDevice sends HTTP DELETE to /devices/:id and purges device', function () {
    $mockHandler = new MockHandler([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'message' => 'Device removed',
        ])),
    ]);

    $config = new Config(
        baseUrl: 'https://gowa.example.com',
        username: 'admin',
        password: 'secretpassword',
    );
    $client = new GowaClient($config, handler: $mockHandler);

    $client->deleteDevice('dev-purge-1');

    $request = $mockHandler->getLastRequest();
    expect($request->getMethod())->toBe('DELETE')
        ->and($request->getUri()->getPath())->toBe('/devices/dev-purge-1');
});

test('reconnectDevice sends HTTP POST to /devices/:id/reconnect', function () {
    $mockHandler = new MockHandler([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'message' => 'Device reconnect triggered',
        ])),
    ]);

    $config = new Config(
        baseUrl: 'https://gowa.example.com',
        username: 'admin',
        password: 'secretpassword',
    );
    $client = new GowaClient($config, handler: $mockHandler);

    $client->reconnectDevice('dev-recon-1');

    $request = $mockHandler->getLastRequest();
    expect($request->getMethod())->toBe('POST')
        ->and($request->getUri()->getPath())->toBe('/devices/dev-recon-1/reconnect');
});

test('checkUser queries /user/check and returns boolean presence status', function () {
    $mockHandler = new MockHandler([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => ['is_on_whatsapp' => true],
        ])),
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => ['is_on_whatsapp' => false],
        ])),
    ]);

    $config = new Config(
        baseUrl: 'https://gowa.example.com',
        username: 'admin',
        password: 'secretpassword',
    );
    $client = new GowaClient($config, handler: $mockHandler);

    $exists = $client->checkUser('dev-1', '5511999998888');
    expect($exists)->toBeTrue();

    $firstRequest = $mockHandler->getLastRequest();
    expect($firstRequest->getMethod())->toBe('GET')
        ->and($firstRequest->getUri()->getPath())->toBe('/user/check')
        ->and($firstRequest->getHeaderLine('X-Device-Id'))->toBe('dev-1');

    $notExists = $client->checkUser('dev-1', '5511000000000');
    expect($notExists)->toBeFalse();
});
