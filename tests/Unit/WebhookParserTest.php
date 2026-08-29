<?php

declare(strict_types=1);

use Gowa\Sdk\Webhook\Event;
use Gowa\Sdk\Webhook\WebhookParser;
use Gowa\Sdk\Webhook\Dto\IncomingMessage;
use Gowa\Sdk\Webhook\Dto\IncomingAck;
use Gowa\Sdk\Webhook\Dto\IncomingReaction;

test('parses message event correctly', function () {
    $json = json_encode([
        'event' => 'message',
        'payload' => [
            'id' => 'WAMID_MSG_123',
            'chat_id' => '5511999998888@s.whatsapp.net',
            'is_from_me' => false,
            'body' => 'Olá! Gostaria de um orçamento.',
            'timestamp' => '2026-08-29T10:00:00Z',
        ],
    ]);

    $parsed = WebhookParser::parse($json);

    expect($parsed['event'])->toBe(Event::Message);
    expect($parsed['event_id'])->toBe('message:WAMID_MSG_123');
    expect($parsed['data'])->toBeInstanceOf(IncomingMessage::class);

    /** @var IncomingMessage $msg */
    $msg = $parsed['data'];
    expect($msg->id)->toBe('WAMID_MSG_123');
    expect($msg->phone)->toBe('5511999998888');
    expect($msg->body)->toBe('Olá! Gostaria de um orçamento.');
    expect($msg->isEcho)->toBeFalse();
});

test('parses message.ack event correctly', function () {
    $payload = [
        'event' => 'message.ack',
        'payload' => [
            'ids' => ['WAMID_1', 'WAMID_2'],
            'receipt_type' => 'read',
            'chat_id' => '5511999998888@s.whatsapp.net',
        ],
    ];

    $parsed = WebhookParser::parse($payload);

    expect($parsed['event'])->toBe(Event::MessageAck);
    expect($parsed['event_id'])->toBe('ack:WAMID_1,WAMID_2:read');
    expect($parsed['data'])->toBeInstanceOf(IncomingAck::class);

    /** @var IncomingAck $ack */
    $ack = $parsed['data'];
    expect($ack->isRead())->toBeTrue();
    expect($ack->messageIds)->toBe(['WAMID_1', 'WAMID_2']);
});

test('parses message.reaction event correctly', function () {
    $payload = [
        'event' => 'message.reaction',
        'payload' => [
            'id' => 'REACT_1',
            'chat_id' => '5511999998888@s.whatsapp.net',
            'reacted_message_id' => 'WAMID_TARGET',
            'reaction' => '👍',
        ],
    ];

    $parsed = WebhookParser::parse($payload);

    expect($parsed['event'])->toBe(Event::MessageReaction);
    expect($parsed['data'])->toBeInstanceOf(IncomingReaction::class);

    /** @var IncomingReaction $reaction */
    $reaction = $parsed['data'];
    expect($reaction->emoji)->toBe('👍');
    expect($reaction->targetMessageId)->toBe('WAMID_TARGET');
});
