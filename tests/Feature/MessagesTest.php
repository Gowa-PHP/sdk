<?php

declare(strict_types=1);

use Gowa\Sdk\Dto\ContactCard;
use Gowa\Sdk\Dto\LocationPayload;
use Gowa\Sdk\Dto\MediaPayload;
use Gowa\Sdk\Dto\MediaType;
use Gowa\Sdk\Dto\MediaUpload;
use Gowa\Sdk\Dto\SentMessage;
use Gowa\Sdk\Exceptions\UnsupportedMediaException;
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
