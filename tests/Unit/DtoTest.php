<?php

declare(strict_types=1);

use Gowa\Sdk\Dto\Avatar;
use Gowa\Sdk\Dto\Device;
use Gowa\Sdk\Dto\Pairing;

test('device dto parses response correctly', function () {
    $deviceData = [
        'code' => 'SUCCESS',
        'results' => [
            'id' => 'device-uuid-123',
            'name' => 'Vendas Matriz',
            'status' => 'logged_in',
            'phone' => '5511999998888',
            'jid' => '5511999998888@s.whatsapp.net',
        ],
    ];

    $device = Device::fromResults($deviceData['results']);

    expect($device->deviceId)->toBe('device-uuid-123')
        ->and($device->name)->toBe('Vendas Matriz')
        ->and($device->status)->toBe('logged_in')
        ->and($device->isPaired())->toBeTrue()
        ->and($device->phone)->toBe('5511999998888');
});

test('pairing dto parses qr link and code correctly', function () {
    $qrData = ['code' => 'SUCCESS', 'results' => ['qr_link' => 'https://gowa.example.com/qr/123']];
    $codeData = ['code' => 'SUCCESS', 'results' => ['pair_code' => 'ABCD-1234']];

    $qrPairing = Pairing::fromQr($qrData['results']);
    $codePairing = Pairing::fromCode($codeData['results']);

    expect($qrPairing->qrLink)->toBe('https://gowa.example.com/qr/123');
    expect($codePairing->pairCode)->toBe('ABCD-1234');
});

test('avatar dto parses avatar url correctly', function () {
    $data = ['url' => 'https://pps.whatsapp.net/v/t61/avatar.jpg', 'id' => 'img-123'];
    $avatar = Avatar::fromResults($data);

    expect($avatar)->not->toBeNull();
    expect($avatar->url)->toBe('https://pps.whatsapp.net/v/t61/avatar.jpg');
    expect($avatar->id)->toBe('img-123');
});

test('media upload constructs from external url and path correctly', function () {
    $urlUpload = \Gowa\Sdk\Dto\MediaUpload::fromUrl('https://example.com/downloads/audio.m4a');
    expect($urlUpload->filename)->toBe('audio.m4a');
    expect($urlUpload->mimeType)->toBe('audio/m4a');

    $tempFile = sys_get_temp_dir() . '/sample.png';
    file_put_contents($tempFile, 'fake png');

    $pathUpload = \Gowa\Sdk\Dto\MediaUpload::fromPath($tempFile, 'image/png');
    expect($pathUpload->filename)->toBe('sample.png');
    expect($pathUpload->mimeType)->toBe('image/png');

    @unlink($tempFile);
});
