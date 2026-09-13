<?php

declare(strict_types=1);

use Gowa\Sdk\Config;
use Gowa\Sdk\Dto\Avatar;
use Gowa\Sdk\Exceptions\GowaRequestException;
use Gowa\Sdk\Exceptions\GowaUnreachableException;
use Gowa\Sdk\GowaClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

// ── Happy path ─────────────────────────────────────────────────────────────

test('avatar returns Avatar DTO when profile picture exists', function () {
    $client = createMockGowaClient([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [
                'url' => 'https://pps.whatsapp.net/v/t61.24694-24/avatar123.jpg',
                'id'  => 'avatar-id-456',
            ],
        ])),
    ]);

    $avatar = $client->avatar('dev-1', '5511999998888');

    expect($avatar)->toBeInstanceOf(Avatar::class);
    expect($avatar->url)->toBe('https://pps.whatsapp.net/v/t61.24694-24/avatar123.jpg');
    expect($avatar->id)->toBe('avatar-id-456');
});

// ── Cases that must return null ("no photo") ───────────────────────────────

test('avatar returns null for 404 response', function () {
    $client = createMockGowaClient([
        new Response(404, [], json_encode(['code' => 'NOT_FOUND', 'message' => 'not found'])),
    ]);

    expect($client->avatar('dev-1', '5511999998888'))->toBeNull();
});

test('avatar returns null when code is different from SUCCESS', function () {
    $client = createMockGowaClient([
        new Response(200, [], json_encode([
            'code'    => 'NO_AVATAR',
            'message' => 'user has no profile picture',
            'results' => [],
        ])),
    ]);

    expect($client->avatar('dev-1', '5511999998888'))->toBeNull();
});

test('avatar returns null when results is empty', function () {
    $client = createMockGowaClient([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [],
        ])),
    ]);

    expect($client->avatar('dev-1', '5511999998888'))->toBeNull();
});

test('avatar returns null when results lacks url', function () {
    $client = createMockGowaClient([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [
                'id' => 'some-id',
            ],
        ])),
    ]);

    expect($client->avatar('dev-1', '5511999998888'))->toBeNull();
});

// ── Cases that must throw (network / server error) ─────────────────────────

test('avatar throws GowaUnreachableException on connection failure or timeout', function () {
    $mockHandler = new MockHandler([
        new ConnectException('Connection timed out after 15000ms', new Request('GET', 'user/avatar')),
    ]);

    $config = new Config(
        baseUrl: 'https://gowa.example.com',
        username: 'admin',
        password: 'secretpassword',
    );
    $client = new GowaClient($config, handler: $mockHandler);

    expect(fn() => $client->avatar('dev-1', '5511999998888'))
        ->toThrow(GowaUnreachableException::class, 'Connection timed out');
});

test('avatar throws GowaRequestException on 500 server error', function () {
    $client = createMockGowaClient([
        new Response(500, [], json_encode([
            'code'    => 'SERVER_ERROR',
            'message' => 'Internal server error occurred',
        ])),
    ]);

    expect(fn() => $client->avatar('dev-1', '5511999998888'))
        ->toThrow(GowaRequestException::class, 'Internal server error occurred');
});

test('avatar throws GowaRequestException on 502 bad gateway', function () {
    $client = createMockGowaClient([
        new Response(502, [], '<html>502 Bad Gateway</html>'),
    ]);

    expect(fn() => $client->avatar('dev-1', '5511999998888'))
        ->toThrow(GowaRequestException::class, '502');
});
