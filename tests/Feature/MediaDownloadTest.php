<?php

declare(strict_types=1);

use Gowa\Sdk\Config;
use Gowa\Sdk\Dto\RemoteMedia;
use Gowa\Sdk\Exceptions\GowaRequestException;
use Gowa\Sdk\Exceptions\GowaSecurityException;
use Gowa\Sdk\Exceptions\GowaUnreachableException;
use Gowa\Sdk\Exceptions\MediaUnavailableException;
use Gowa\Sdk\GowaClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

// ── describeMedia (Item 5) ──────────────────────────────────────────────────

test('describeMedia returns RemoteMedia dto for single string phone', function () {
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
    expect($media->mimeType)->toBeNull();
    expect($media->filename)->toBe('1787663669-b8e6a3b4');
});

test('describeMedia resolves echo on second candidate phone when first does not belong to chat', function () {
    $mockHandler = new MockHandler([
        // 1st attempt with sender phone returns does not belong to chat
        new Response(400, [], json_encode([
            'code'    => 'FAILED',
            'message' => 'message does not belong to chat',
        ])),
        // 2nd attempt with recipient phone succeeds
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [
                'file_path' => '/storage/media/echo-solved.mp3',
                'file_size' => 55000,
                'filename'  => 'audio-echo',
            ],
        ])),
    ]);

    $config = new Config(
        baseUrl: 'https://gowa.example.com',
        username: 'admin',
        password: 'secretpassword',
    );
    $client = new GowaClient($config, handler: $mockHandler);

    $media = $client->describeMedia('device-uuid-1', ['5511888888888', '5511999999999'], 'WAMID_ECHO');

    expect($media)->toBeInstanceOf(RemoteMedia::class);
    expect($media->url)->toBe('/storage/media/echo-solved.mp3');
    expect($media->sizeBytes)->toBe(55000);
});

test('describeMedia throws MediaUnavailableException on permanent refusal does not contain downloadable media', function () {
    $client = createMockGowaClient([
        new Response(400, [], json_encode([
            'code'    => 'FAILED',
            'message' => 'message does not contain downloadable media',
        ])),
    ]);

    expect(fn() => $client->describeMedia('device-uuid-1', '5511999998888', 'TEXT_MSG_WITHOUT_MEDIA'))
        ->toThrow(MediaUnavailableException::class, 'does not contain downloadable media');
});

test('describeMedia throws MediaUnavailableException on permanent refusal unsupported media type', function () {
    $client = createMockGowaClient([
        new Response(400, [], json_encode([
            'code'    => 'FAILED',
            'message' => 'unsupported media type provided',
        ])),
    ]);

    expect(fn() => $client->describeMedia('device-uuid-1', '5511999998888', 'UNSUPPORTED_MSG'))
        ->toThrow(MediaUnavailableException::class, 'unsupported media type');
});

test('describeMedia throws MediaUnavailableException on permanent refusal not found', function () {
    $client = createMockGowaClient([
        new Response(400, [], json_encode([
            'code'    => 'FAILED',
            'message' => 'media not found on device',
        ])),
    ]);

    expect(fn() => $client->describeMedia('device-uuid-1', '5511999998888', 'NOT_FOUND_MSG'))
        ->toThrow(MediaUnavailableException::class, 'not found');
});

test('describeMedia returns null for 404 response', function () {
    $client = createMockGowaClient([
        new Response(404, [], json_encode(['code' => 'NOT_FOUND'])),
    ]);

    $media = $client->describeMedia('device-uuid-1', '5511999998888', 'EXPIRED_MSG');

    expect($media)->toBeNull();
});

test('describeMedia throws GowaRequestException when all candidate phones are exhausted', function () {
    $client = createMockGowaClient([
        new Response(400, [], json_encode([
            'code'    => 'FAILED',
            'message' => 'message does not belong to chat',
        ])),
        new Response(400, [], json_encode([
            'code'    => 'FAILED',
            'message' => 'message does not belong to chat',
        ])),
    ]);

    expect(fn() => $client->describeMedia('device-uuid-1', ['5511111111111', '5511222222222'], 'WAMID_UNKNOWN'))
        ->toThrow(GowaRequestException::class, 'does not belong to chat');
});

test('describeMedia prefers relative file_path over absolute file_url', function () {
    $client = createMockGowaClient([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [
                'file_url'  => 'http://gowa.internal:3000/storage/media/insecure-http',
                'file_path' => '/storage/media/secure-relative',
                'file_size' => 1234,
            ],
        ])),
    ]);

    $media = $client->describeMedia('device-uuid-1', '5511999998888', 'WAMID_MSG_123');

    expect($media->url)->toBe('/storage/media/secure-relative');
});

// ── downloadMedia (Item 3) ──────────────────────────────────────────────────

test('downloadMedia forwards custom timeout to Guzzle request', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'gowa_test_');

    try {
        $mockHandler = new MockHandler([
            new Response(200, [], 'media binary content'),
        ]);

        $config = new Config(
            baseUrl: 'https://gowa.example.com',
            username: 'admin',
            password: 'secretpassword',
            timeout: 15,
        );

        $client = new GowaClient($config, handler: $mockHandler);

        $client->downloadMedia('https://gowa.example.com/media/file.mp4', $tempFile, timeout: 120);

        $lastOptions = $mockHandler->getLastOptions();
        expect($lastOptions['timeout'] ?? null)->toBe(120);
        expect(file_get_contents($tempFile))->toBe('media binary content');
    } finally {
        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }
});

test('downloadMedia deletes partial file and includes response body in exception on 4xx', function () {
    $tempFile = sys_get_temp_dir() . '/gowa_partial_' . uniqid() . '.bin';

    $client = createMockGowaClient([
        new Response(404, [], 'File not found on storage backend'),
    ]);

    try {
        $client->downloadMedia('https://gowa.example.com/media/missing.bin', $tempFile);
        $this->fail('Expected GowaRequestException to be thrown');
    } catch (GowaRequestException $e) {
        expect($e->statusCode)->toBe(404);
        expect($e->getMessage())->toContain('404');
        expect($e->getMessage())->toContain('File not found on storage backend');
        expect(file_exists($tempFile))->toBeFalse();
    }
});

test('downloadMedia deletes partial file and throws GowaUnreachableException on network failure', function () {
    $tempFile = sys_get_temp_dir() . '/gowa_partial_' . uniqid() . '.bin';
    file_put_contents($tempFile, 'corrupted partial content');

    $mockHandler = new MockHandler([
        new ConnectException('Connection reset by peer', new Request('GET', 'https://gowa.example.com/media/file.mp4')),
    ]);

    $config = new Config(
        baseUrl: 'https://gowa.example.com',
        username: 'admin',
        password: 'secretpassword',
    );
    $client = new GowaClient($config, handler: $mockHandler);

    expect(fn() => $client->downloadMedia('https://gowa.example.com/media/file.mp4', $tempFile))
        ->toThrow(GowaUnreachableException::class, 'Connection reset by peer');

    expect(file_exists($tempFile))->toBeFalse();
});

test('downloadMedia enforces GowaHost::assertBelongsToServer before request', function () {
    $client = createMockGowaClient([]);

    expect(fn() => $client->downloadMedia('https://malicious-server.com/steal-creds', '/tmp/target'))
        ->toThrow(GowaSecurityException::class);
});
