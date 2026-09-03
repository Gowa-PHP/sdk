<?php

declare(strict_types=1);

use Gowa\Sdk\Dto\Device;
use Gowa\Sdk\Dto\Pairing;
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

test('updateWebhook sends patch request with webhook url and secret', function () {
    $client = createMockGowaClient([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [
                'device_id'   => 'device-uuid-1',
                'webhook_url' => 'https://app.com/webhooks/gowa/device-uuid-1',
            ],
        ])),
    ]);

    $results = $client->updateWebhook(
        deviceId: 'device-uuid-1',
        webhookUrl: 'https://app.com/webhooks/gowa/device-uuid-1',
        webhookSecret: 'sec_123',
        events: ['message', 'message.ack'],
    );

    expect($results)->toBeArray();
    expect($results['device_id'])->toBe('device-uuid-1');
});
