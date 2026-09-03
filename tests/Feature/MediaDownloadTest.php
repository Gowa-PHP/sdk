<?php

declare(strict_types=1);

use Gowa\Sdk\Dto\RemoteMedia;
use GuzzleHttp\Psr7\Response;

test('describeMedia returns RemoteMedia dto for valid response', function () {
    $client = createMockGowaClient([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [
                'file_path' => '/storage/media/1787663669-b8e6a3b4',
                'file_size' => 102450,
                'filename'  => '1787663669-b8e6a3b4',
            ],
        ])),
    ]);

    $media = $client->describeMedia('device-uuid-1', '5511999998888', 'WAMID_MSG_123');

    expect($media)->toBeInstanceOf(RemoteMedia::class);
    expect($media->url)->toBe('/storage/media/1787663669-b8e6a3b4');
    expect($media->sizeBytes)->toBe(102450);
});

test('describeMedia returns null for 404 response', function () {
    $client = createMockGowaClient([
        new Response(404, [], json_encode(['code' => 'NOT_FOUND'])),
    ]);

    $media = $client->describeMedia('device-uuid-1', '5511999998888', 'EXPIRED_MSG');

    expect($media)->toBeNull();
});
