<?php

declare(strict_types=1);

use Gowa\Sdk\Config;
use Gowa\Sdk\Dto\ContactCard;
use Gowa\Sdk\Dto\LocationPayload;
use Gowa\Sdk\Dto\MediaPayload;
use Gowa\Sdk\Dto\MediaType;
use Gowa\Sdk\Dto\MediaUpload;
use Gowa\Sdk\Dto\SentMessage;
use Gowa\Sdk\Exceptions\GowaRequestException;
use Gowa\Sdk\Exceptions\UnsupportedMediaException;
use Gowa\Sdk\GowaClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

test('sendText sends text message to formatted jid', function () {
    $client = createMockGowaClient([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [
                'message_id' => 'WAMID_987654321',
            ],
        ])),
    ]);

    $sent = $client->sendText('device-uuid-1', '5511999998888', 'Olá! Como posso ajudar?');

    expect($sent)->toBeInstanceOf(SentMessage::class);
    expect($sent->providerMessageId)->toBe('WAMID_987654321');
});

test('sendMedia validates mime type before sending', function () {
    $client = createMockGowaClient([]);

    $tempFile = sys_get_temp_dir() . '/test_video.3gp';
    file_put_contents($tempFile, 'fake 3gp video bytes');

    $upload = new MediaUpload($tempFile, 'video/3gpp', 'test_video.3gp');
    $media = new MediaPayload(MediaType::Video, $upload);

    expect(fn() => $client->sendMedia('device-uuid-1', '5511999998888', $media))
        ->toThrow(UnsupportedMediaException::class);

    @unlink($tempFile);
});

test('sendLocation sends location payload', function () {
    $client = createMockGowaClient([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [
                'message_id' => 'WAMID_LOC_123',
            ],
        ])),
    ]);

    $location = new LocationPayload(-23.550520, -46.633308);
    $sent = $client->sendLocation('device-uuid-1', '5511999998888', $location);

    expect($sent->providerMessageId)->toBe('WAMID_LOC_123');
});

test('sendContacts sends contact card', function () {
    $client = createMockGowaClient([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [
                'message_id' => 'WAMID_CONTACT_123',
            ],
        ])),
    ]);

    $contact = new ContactCard('João da Silva', [['phone' => '+5511988887777']]);
    $sent = $client->sendContacts('device-uuid-1', '5511999998888', [$contact]);

    expect($sent->providerMessageId)->toBe('WAMID_CONTACT_123');
});

test('markRead sends read confirmation', function () {
    $client = createMockGowaClient([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [],
        ])),
    ]);

    expect(fn() => $client->markRead('device-uuid-1', '5511999998888', 'WAMID_123'))
        ->not->toThrow(Exception::class);
});

test('markRead withTyping sends presence and confirms read', function () {
    $client = createMockGowaClient([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [],
        ])),
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [],
        ])),
    ]);

    expect(fn() => $client->markRead('device-uuid-1', '5511999998888', 'WAMID_123', withTyping: true))
        ->not->toThrow(Exception::class);
});

test('markRead withTyping throws when presence request fails', function () {
    $client = createMockGowaClient([
        new Response(500, [], json_encode([
            'code'    => 'SERVER_ERROR',
            'message' => 'failed to start presence',
        ])),
    ]);

    expect(fn() => $client->markRead('device-uuid-1', '5511999998888', 'WAMID_123', withTyping: true))
        ->toThrow(GowaRequestException::class, 'failed to start presence');
});

test('forwardMessage forwards message', function () {
    $client = createMockGowaClient([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => ['message_id' => 'WAMID_FWD_123'],
        ])),
    ]);

    $sent = $client->forwardMessage('device-uuid-1', '5511999998888', 'WAMID_OLD_1');
    expect($sent->providerMessageId)->toBe('WAMID_FWD_123');
});

test('sendLink sends url preview message', function () {
    $client = createMockGowaClient([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => ['message_id' => 'WAMID_LINK_123'],
        ])),
    ]);

    $sent = $client->sendLink('device-uuid-1', '5511999998888', 'https://fazz.ai', 'Confira nosso site');
    expect($sent->providerMessageId)->toBe('WAMID_LINK_123');
});

test('sendPoll sends interactive poll', function () {
    $client = createMockGowaClient([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => ['message_id' => 'WAMID_POLL_123'],
        ])),
    ]);

    $sent = $client->sendPoll('device-uuid-1', '5511999998888', 'Qual seu horário preferido?', ['Manhã', 'Tarde', 'Noite']);
    expect($sent->providerMessageId)->toBe('WAMID_POLL_123');
});

test('editMessage edits sent message text', function () {
    $client = createMockGowaClient([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => ['message_id' => 'WAMID_EDITED_123'],
        ])),
    ]);

    $sent = $client->editMessage('device-uuid-1', '5511999998888', 'WAMID_ORIGINAL', 'Texto corrigido');
    expect($sent->providerMessageId)->toBe('WAMID_EDITED_123');
});

test('revokeMessage revokes message for everyone', function () {
    $client = createMockGowaClient([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [],
        ])),
    ]);

    expect(fn() => $client->revokeMessage('device-uuid-1', '5511999998888', 'WAMID_123'))
        ->not->toThrow(Exception::class);
});

test('sendText sends mentions, duration, and schedule options', function () {
    $mockHandler = new MockHandler([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [
                'schedule_id'  => 'SCHED-TEXT-1',
                'scheduled_at' => '2026-10-01T09:00:00Z',
            ],
        ])),
    ]);

    $config = new Config(
        baseUrl: 'https://gowa.example.com',
        username: 'admin',
        password: 'secretpassword',
    );
    $client = new GowaClient($config, handler: $mockHandler);

    $schedule = new \Gowa\Sdk\Dto\ScheduleOptions(
        scheduledAt: '2026-10-01T09:00:00Z',
        timezone: 'America/Sao_Paulo',
        recurrence: 'daily',
    );

    $sent = $client->sendText(
        deviceId: 'dev-1',
        to: '5511999998888',
        text: 'Atenção @everyone',
        mentions: ['5511999998888', '@everyone'],
        duration: 86400,
        schedule: $schedule,
    );

    expect($sent->isScheduled())->toBeTrue()
        ->and($sent->scheduleId)->toBe('SCHED-TEXT-1')
        ->and($sent->scheduledAt)->toBe('2026-10-01T09:00:00Z');

    $request = $mockHandler->getLastRequest();
    $body = json_decode((string) $request->getBody(), true);

    expect($body['phone'])->toBe('5511999998888@s.whatsapp.net')
        ->and($body['message'])->toBe('Atenção @everyone')
        ->and($body['mentions'])->toBe(['5511999998888', '@everyone'])
        ->and($body['duration'])->toBe(86400)
        ->and($body['scheduled_at'])->toBe('2026-10-01T09:00:00Z')
        ->and($body['timezone'])->toBe('America/Sao_Paulo')
        ->and($body['recurrence'])->toBe('daily');
});

test('sendMedia sends view_once, mentions, and schedule options in multipart', function () {
    $mockHandler = new MockHandler([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [
                'schedule_id'  => 'SCHED-MEDIA-1',
                'scheduled_at' => '2026-10-02T10:00:00Z',
            ],
        ])),
    ]);

    $config = new Config(
        baseUrl: 'https://gowa.example.com',
        username: 'admin',
        password: 'secretpassword',
    );
    $client = new GowaClient($config, handler: $mockHandler);

    $tempFile = sys_get_temp_dir() . '/temp_img.jpg';
    file_put_contents($tempFile, 'fake jpg');

    $upload = \Gowa\Sdk\Dto\MediaUpload::fromPath($tempFile, 'image/jpeg');
    $media = new \Gowa\Sdk\Dto\MediaPayload(
        type: \Gowa\Sdk\Dto\MediaType::Image,
        upload: $upload,
        caption: 'Olha isso @5511999998888',
        mentions: ['5511999998888', '@everyone'],
        viewOnce: true,
    );

    $schedule = new \Gowa\Sdk\Dto\ScheduleOptions(
        scheduledAt: '2026-10-02T10:00:00Z',
        timezone: 'America/Sao_Paulo',
        recurrence: 'weekly',
        weekdays: [1, 3],
    );

    $sent = $client->sendMedia(
        deviceId: 'dev-1',
        to: '5511999998888',
        media: $media,
        schedule: $schedule,
    );

    @unlink($tempFile);

    expect($sent->isScheduled())->toBeTrue()
        ->and($sent->scheduleId)->toBe('SCHED-MEDIA-1');

    $request = $mockHandler->getLastRequest();
    $rawBody = (string) $request->getBody();

    expect($rawBody)->toContain('name="view_once"')
        ->and($rawBody)->toContain('name="mentions"')
        ->and($rawBody)->toContain('5511999998888')
        ->and($rawBody)->toContain('@everyone')
        ->and($rawBody)->toContain('name="scheduled_at"')
        ->and($rawBody)->toContain('2026-10-02T10:00:00Z')
        ->and($rawBody)->toContain('name="timezone"')
        ->and($rawBody)->toContain('America/Sao_Paulo')
        ->and($rawBody)->toContain('name="weekdays"')
        ->and($rawBody)->toContain('1')
        ->and($rawBody)->toContain('3');
});

test('forwardMessage supports schedule options', function () {
    $mockHandler = new MockHandler([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [
                'schedule_id' => 'SCHED-FWD-1',
            ],
        ])),
    ]);

    $config = new Config(
        baseUrl: 'https://gowa.example.com',
        username: 'admin',
        password: 'secretpassword',
    );
    $client = new GowaClient($config, handler: $mockHandler);

    $schedule = new \Gowa\Sdk\Dto\ScheduleOptions(
        scheduledAt: '2026-10-05T08:00:00Z',
        timezone: 'UTC',
    );

    $sent = $client->forwardMessage(
        deviceId: 'dev-1',
        to: '5511999998888',
        providerMessageId: 'ORIG_WAMID_1',
        schedule: $schedule,
    );

    expect($sent->isScheduled())->toBeTrue()
        ->and($sent->scheduleId)->toBe('SCHED-FWD-1');

    $request = $mockHandler->getLastRequest();
    $body = json_decode((string) $request->getBody(), true);
    expect($body['scheduled_at'])->toBe('2026-10-05T08:00:00Z')
        ->and($body['timezone'])->toBe('UTC');
});

test('requestChatHistory posts count to /chat/:jid/history', function () {
    $mockHandler = new MockHandler([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'message' => 'Chat history request sent to phone',
            'results' => [
                'chat_jid' => '5511999998888@s.whatsapp.net',
                'count'    => 100,
            ],
        ])),
    ]);

    $config = new Config(
        baseUrl: 'https://gowa.example.com',
        username: 'admin',
        password: 'secretpassword',
    );
    $client = new GowaClient($config, handler: $mockHandler);

    $results = $client->requestChatHistory('dev-1', '5511999998888', count: 100);

    expect($results['count'])->toBe(100);

    $request = $mockHandler->getLastRequest();
    expect($request->getMethod())->toBe('POST')
        ->and($request->getHeaderLine('X-Device-Id'))->toBe('dev-1')
        ->and($request->getUri()->getPath())->toBe('/chat/5511999998888@s.whatsapp.net/history');
});
