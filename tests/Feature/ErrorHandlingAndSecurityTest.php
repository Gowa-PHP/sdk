<?php

declare(strict_types=1);

use Gowa\Sdk\Config;
use Gowa\Sdk\Dto\MediaPayload;
use Gowa\Sdk\Dto\MediaType;
use Gowa\Sdk\Dto\MediaUpload;
use Gowa\Sdk\Exceptions\GowaRequestException;
use Gowa\Sdk\Exceptions\GowaUnreachableException;
use Gowa\Sdk\GowaClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

// ── Item 4 & 8: Structured GowaRequestException & GowaUnreachableException ───

test('connection failure throws GowaUnreachableException for post request', function () {
    $mockHandler = new MockHandler([
        new ConnectException('Failed to connect to gowa host', new Request('POST', 'send/message')),
    ]);

    $config = new Config(
        baseUrl: 'https://gowa.example.com',
        username: 'admin',
        password: 'secretpassword',
    );
    $client = new GowaClient($config, handler: $mockHandler);

    expect(fn() => $client->sendText('dev-1', '5511999998888', 'Hello'))
        ->toThrow(GowaUnreachableException::class, 'Failed to connect to gowa host');
});

test('HTTP 400 validation error builds descriptive exception with status, gowaCode, and gowaMessage', function () {
    $client = createMockGowaClient([
        new Response(400, [], json_encode([
            'code'    => 'VALIDATION_ERROR',
            'message' => 'your audio type is not allowed. please use (audio/aac,audio/amr,audio/flac,audio/m4a,...)',
        ])),
    ]);

    try {
        $client->sendText('dev-1', '5511999998888', 'Hello');
        $this->fail('Expected GowaRequestException');
    } catch (GowaRequestException $e) {
        expect($e->statusCode)->toBe(400);
        expect($e->getStatusCode())->toBe(400);
        expect($e->gowaCode)->toBe('VALIDATION_ERROR');
        expect($e->getGowaCode())->toBe('VALIDATION_ERROR');
        expect($e->gowaMessage)->toBe('your audio type is not allowed. please use (audio/aac,audio/amr,audio/flac,audio/m4a,...)');
        expect($e->getGowaMessage())->toBe('your audio type is not allowed. please use (audio/aac,audio/amr,audio/flac,audio/m4a,...)');
        expect($e->getMessage())->toBe('gowa refused send text message: 400 VALIDATION_ERROR your audio type is not allowed. please use (audio/aac,audio/amr,audio/flac,audio/m4a,...)');
    }
});

test('HTTP 502 non-JSON error includes status and up to 2KB of raw body in exception', function () {
    $htmlBody = '<html><body><h1>502 Bad Gateway</h1><p>Nginx proxy failed</p></body></html>';

    $client = createMockGowaClient([
        new Response(502, [], $htmlBody),
    ]);

    try {
        $client->sendText('dev-1', '5511999998888', 'Hello');
        $this->fail('Expected GowaRequestException');
    } catch (GowaRequestException $e) {
        expect($e->statusCode)->toBe(502);
        expect($e->gowaCode)->toBeNull();
        expect($e->gowaMessage)->toBe($htmlBody);
        expect($e->getMessage())->toContain('502');
        expect($e->getMessage())->toContain($htmlBody);
    }
});

test('sendMedia throws GowaUnreachableException on network failure', function () {
    $mockHandler = new MockHandler([
        new ConnectException('Network timeout while streaming media', new Request('POST', 'send/image')),
    ]);

    $config = new Config(
        baseUrl: 'https://gowa.example.com',
        username: 'admin',
        password: 'secretpassword',
    );
    $client = new GowaClient($config, handler: $mockHandler);

    $media = new MediaPayload(
        type: MediaType::Image,
        upload: MediaUpload::fromPath(__FILE__, 'image/jpeg', 'test.jpg'),
    );

    expect(fn() => $client->sendMedia('dev-1', '5511999998888', $media))
        ->toThrow(GowaUnreachableException::class, 'Network timeout while streaming media');
});

test('fetchQrImage throws GowaUnreachableException on network failure and GowaRequestException on 4xx', function () {
    $mockHandler = new MockHandler([
        new ConnectException('DNS lookup failed', new Request('GET', 'https://gowa.example.com/qr/dev-1')),
        new Response(404, [], 'QR expired'),
    ]);

    $config = new Config(
        baseUrl: 'https://gowa.example.com',
        username: 'admin',
        password: 'secretpassword',
    );
    $client = new GowaClient($config, handler: $mockHandler);

    // First attempt: network failure
    expect(fn() => $client->fetchQrImage('https://gowa.example.com/qr/dev-1'))
        ->toThrow(GowaUnreachableException::class, 'DNS lookup failed');

    // Second attempt: 404
    expect(fn() => $client->fetchQrImage('https://gowa.example.com/qr/dev-1'))
        ->toThrow(GowaRequestException::class, '404 QR expired');
});

// ── Item 10: Reject empty deviceId ──────────────────────────────────────────

test('sendText rejects empty or whitespace deviceId and does not execute HTTP request', function (string $invalidDeviceId) {
    $mockHandler = new MockHandler([]);

    $config = new Config(
        baseUrl: 'https://gowa.example.com',
        username: 'admin',
        password: 'secretpassword',
    );
    $client = new GowaClient($config, handler: $mockHandler);

    expect(fn() => $client->sendText($invalidDeviceId, '5511999998888', 'Hello'))
        ->toThrow(InvalidArgumentException::class, 'Device ID cannot be empty or whitespace.');

    expect($mockHandler->count())->toBe(0);
})->with([
    '',
    ' ',
    '   ',
    "\t",
    "\n",
]);

test('all deviceId-scoped methods reject empty deviceId without calling server', function () {
    $mockHandler = new MockHandler([]);
    $config = new Config(
        baseUrl: 'https://gowa.example.com',
        username: 'admin',
        password: 'secretpassword',
    );
    $client = new GowaClient($config, handler: $mockHandler);

    expect(fn() => $client->createDevice('', 'https://url', 'sec', []))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->updateWebhook('', 'https://url'))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->startQrPairing(''))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->startCodePairing('', '5511999998888'))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->device(''))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->logout(''))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->avatar('', '5511999998888'))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->sendLocation('', '5511999998888', new \Gowa\Sdk\Dto\LocationPayload(latitude: 0, longitude: 0)))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->sendContacts('', '5511999998888', [new \Gowa\Sdk\Dto\ContactCard(name: 'Test')]))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->sendReaction('', '5511999998888', 'WAMID', '👍'))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->forwardMessage('', '5511999998888', 'WAMID'))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->sendLink('', '5511999998888', 'https://example.com'))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->sendPoll('', '5511999998888', 'Q', ['A', 'B']))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->editMessage('', '5511999998888', 'WAMID', 'edited'))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->revokeMessage('', '5511999998888', 'WAMID'))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->deleteMessage('', '5511999998888', 'WAMID'))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->starMessage('', '5511999998888', 'WAMID'))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->markPlayed('', '5511999998888', 'WAMID'))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->markRead('', '5511999998888', 'WAMID'))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->describeMedia('', '5511999998888', 'WAMID'))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->deleteDevice(''))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->reconnectDevice(''))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->checkUser('', '5511999998888'))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->requestChatHistory('', '5511999998888'))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->listSchedules(''))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->getSchedule('', 'SCHED-1'))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->pauseSchedule('', 'SCHED-1'))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->resumeSchedule('', 'SCHED-1'))->toThrow(InvalidArgumentException::class);
    expect(fn() => $client->cancelSchedule('', 'SCHED-1'))->toThrow(InvalidArgumentException::class);

    expect($mockHandler->count())->toBe(0);
});

test('all scheduleId-scoped methods reject empty or whitespace scheduleId without calling server', function (string $invalidScheduleId) {
    $mockHandler = new MockHandler([]);
    $config = new Config(
        baseUrl: 'https://gowa.example.com',
        username: 'admin',
        password: 'secretpassword',
    );
    $client = new GowaClient($config, handler: $mockHandler);

    expect(fn() => $client->getSchedule('dev-1', $invalidScheduleId))
        ->toThrow(InvalidArgumentException::class, 'Schedule ID cannot be empty or whitespace.');
    expect(fn() => $client->pauseSchedule('dev-1', $invalidScheduleId))
        ->toThrow(InvalidArgumentException::class, 'Schedule ID cannot be empty or whitespace.');
    expect(fn() => $client->resumeSchedule('dev-1', $invalidScheduleId))
        ->toThrow(InvalidArgumentException::class, 'Schedule ID cannot be empty or whitespace.');
    expect(fn() => $client->cancelSchedule('dev-1', $invalidScheduleId))
        ->toThrow(InvalidArgumentException::class, 'Schedule ID cannot be empty or whitespace.');

    expect($mockHandler->count())->toBe(0);
})->with([
    '',
    ' ',
    '   ',
    "\t",
    "\n",
]);

test('path parameters reject path traversal and dangerous characters without calling server', function (string $traversal) {
    $mockHandler = new MockHandler([]);
    $config = new Config(
        baseUrl: 'https://gowa.example.com',
        username: 'admin',
        password: 'secretpassword',
    );
    $client = new GowaClient($config, handler: $mockHandler);

    expect(fn() => $client->deleteDevice("dev{$traversal}"))
        ->toThrow(InvalidArgumentException::class, 'Device ID contains invalid path characters.');
    expect(fn() => $client->reconnectDevice("dev{$traversal}"))
        ->toThrow(InvalidArgumentException::class, 'Device ID contains invalid path characters.');
    expect(fn() => $client->getSchedule('dev-1', "sched{$traversal}"))
        ->toThrow(InvalidArgumentException::class, 'Schedule ID contains invalid path characters.');
    expect(fn() => $client->deleteMessage('dev-1', '5511999998888', "msg{$traversal}"))
        ->toThrow(InvalidArgumentException::class, 'Message ID contains invalid path characters.');
    expect(fn() => $client->requestChatHistory('dev-1', "5511999998888@s.whatsapp.net{$traversal}"))
        ->toThrow(InvalidArgumentException::class, 'Chat JID contains invalid path characters.');

    expect($mockHandler->count())->toBe(0);
})->with([
    '/../other',
    '\\..\\other',
    '/admin',
    '?query=inject',
    '#fragment',
]);

test('non-schedulable endpoint rejects response returning only schedule_id', function () {
    $client = createMockGowaClient([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'message' => 'Reaction queued',
            'results' => [
                'schedule_id' => 'SCHED-UNEXPECTED-1',
            ],
        ])),
    ]);

    expect(fn() => $client->sendReaction('dev-1', '5511999998888', 'WAMID-1', '👍'))
        ->toThrow(GowaRequestException::class, 'gowa accepted send reaction without returning a message_id.');
});
