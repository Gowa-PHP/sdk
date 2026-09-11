<?php

declare(strict_types=1);

use Gowa\Sdk\Webhook\Dto\IncomingAck;
use Gowa\Sdk\Webhook\Dto\IncomingMessage;
use Gowa\Sdk\Webhook\Dto\IncomingReaction;
use Gowa\Sdk\Webhook\Event;
use Gowa\Sdk\Webhook\WebhookParser;

test('parses message event correctly', function () {
    $json = json_encode([
        'event'   => 'message',
        'payload' => [
            'id'         => 'WAMID_MSG_123',
            'chat_id'    => '5511999998888@s.whatsapp.net',
            'is_from_me' => false,
            'body'       => 'Olá! Gostaria de um orçamento.',
            'timestamp'  => '2026-08-29T10:00:00Z',
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
        'event'   => 'message.ack',
        'payload' => [
            'ids'          => ['WAMID_1', 'WAMID_2'],
            'receipt_type' => 'read',
            'chat_id'      => '5511999998888@s.whatsapp.net',
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
        'event'   => 'message.reaction',
        'payload' => [
            'id'                 => 'REACT_1',
            'chat_id'            => '5511999998888@s.whatsapp.net',
            'reacted_message_id' => 'WAMID_TARGET',
            'reaction'           => '👍',
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

test('parses live_location message event correctly', function () {
    $payload = [
        'event'     => 'message',
        'device_id' => '628123456789@s.whatsapp.net',
        'payload'   => [
            'id'            => '94D13237B4D7F33EE4A63228BBD79EC0',
            'chat_id'       => '5511999998888@s.whatsapp.net',
            'from'          => '5511999998888@s.whatsapp.net',
            'from_name'     => 'João',
            'timestamp'     => '2026-09-11T10:00:00Z',
            'live_location' => [
                'degreesLatitude'                   => -23.55052,
                'degreesLongitude'                  => -46.633308,
                'accuracyInMeters'                  => 15,
                'speedInMps'                        => 1.2,
                'degreesClockwiseFromMagneticNorth' => 180,
                'caption'                           => 'A caminho!',
                'sequenceNumber'                    => 1,
                'timeOffset'                        => 0,
            ],
        ],
    ];

    $parsed = WebhookParser::parse($payload);

    expect($parsed['event'])->toBe(Event::Message);
    expect($parsed['event_id'])->toBe('message:94D13237B4D7F33EE4A63228BBD79EC0');
    expect($parsed['data'])->toBeInstanceOf(IncomingMessage::class);

    /** @var IncomingMessage $msg */
    $msg = $parsed['data'];
    expect($msg->id)->toBe('94D13237B4D7F33EE4A63228BBD79EC0');
    expect($msg->phone)->toBe('5511999998888');
    expect($msg->senderName)->toBe('João');
    expect($msg->type)->toBe('live_location');
    expect($msg->body)->toBe('A caminho!');
    expect($msg->isLocation())->toBeTrue();
    expect($msg->isLiveLocation())->toBeTrue();

    $liveLoc = $msg->liveLocation();
    expect($liveLoc)->not->toBeNull();
    expect($liveLoc->latitude)->toBe(-23.55052);
    expect($liveLoc->longitude)->toBe(-46.633308);
    expect($liveLoc->accuracyInMeters)->toBe(15);
    expect($liveLoc->speedInMps)->toBe(1.2);
    expect($liveLoc->degreesClockwiseFromMagneticNorth)->toBe(180);
    expect($liveLoc->caption)->toBe('A caminho!');
    expect($liveLoc->sequenceNumber)->toBe(1);
    expect($liveLoc->timeOffset)->toBe(0);

    $loc = $msg->location();
    expect($loc)->not->toBeNull();
    expect($loc->latitude)->toBe(-23.55052);
    expect($loc->longitude)->toBe(-46.633308);
});

test('parses static location message event correctly', function () {
    $payload = [
        'event'     => 'message',
        'device_id' => '628123456789@s.whatsapp.net',
        'payload'   => [
            'id'        => 'LOC_MSG_456',
            'chat_id'   => '5511999998888@s.whatsapp.net',
            'timestamp' => '2026-09-11T10:00:00Z',
            'location'  => [
                'degreesLatitude'  => -23.55052,
                'degreesLongitude' => -46.633308,
            ],
        ],
    ];

    $parsed = WebhookParser::parse($payload);
    /** @var IncomingMessage $msg */
    $msg = $parsed['data'];

    expect($msg->type)->toBe('location');
    expect($msg->isLocation())->toBeTrue();
    expect($msg->isLiveLocation())->toBeFalse();
    expect($msg->liveLocation())->toBeNull();
    expect($msg->location())->not->toBeNull();
    expect($msg->location()->latitude)->toBe(-23.55052);
    expect($msg->location()->longitude)->toBe(-46.633308);
});

test('parses poll message correctly', function () {
    $payload = [
        'event'     => 'message',
        'device_id' => '628123456789@s.whatsapp.net',
        'payload'   => [
            'id'      => 'POLL_MSG_1',
            'chat_id' => '5511999998888@s.whatsapp.net',
            'poll'    => [
                'type'     => 'creation',
                'poll_id'  => 'POLL_MSG_1',
                'question' => 'Qual o melhor dia?',
                'options'  => [
                    ['name' => 'Sexta'],
                    ['name' => 'Sábado'],
                ],
                'selectable_options_count' => 1,
            ],
        ],
    ];

    $parsed = WebhookParser::parse($payload);
    /** @var IncomingMessage $msg */
    $msg = $parsed['data'];

    expect($msg->type)->toBe('poll');
    expect($msg->isPoll())->toBeTrue();
    expect($msg->body)->toBe('Qual o melhor dia?');

    $poll = $msg->poll();
    expect($poll)->not->toBeNull();
    expect($poll->question)->toBe('Qual o melhor dia?');
    expect($poll->isCreation())->toBeTrue();
    expect($poll->options)->toHaveCount(2);
});

test('parses event message correctly in 1:1 or group chat', function () {
    $payload = [
        'event'     => 'message',
        'device_id' => '628123456789@s.whatsapp.net',
        'payload'   => [
            'id'            => 'EVT_MSG_1',
            'chat_id'       => '5511999998888@s.whatsapp.net',
            'event_message' => [
                'name'        => 'Reunião 1:1 de Feedback',
                'description' => 'Alinhamento semanal',
                'start_time'  => 1757599200,
                'end_time'    => 1757602800,
                'call_link'   => 'https://call.whatsapp.com/video/abc',
            ],
        ],
    ];

    $parsed = WebhookParser::parse($payload);
    /** @var IncomingMessage $msg */
    $msg = $parsed['data'];

    expect($msg->type)->toBe('event');
    expect($msg->isEvent())->toBeTrue();
    expect($msg->body)->toBe('Reunião 1:1 de Feedback');

    $event = $msg->event();
    expect($event)->not->toBeNull();
    expect($event->name)->toBe('Reunião 1:1 de Feedback');
    expect($event->startTime)->toBe(1757599200);
    expect($event->callLink)->toBe('https://call.whatsapp.com/video/abc');
});

test('parses order message correctly', function () {
    $payload = [
        'event'     => 'message',
        'device_id' => '628123456789@s.whatsapp.net',
        'payload'   => [
            'id'      => 'ORDER_MSG_1',
            'chat_id' => '5511999998888@s.whatsapp.net',
            'order'   => [
                'order_id'            => 'ORD_1234',
                'order_title'         => 'Camisa Social',
                'item_count'          => 2,
                'total_amount_1000'   => 199900,
                'total_currency_code' => 'BRL',
            ],
        ],
    ];

    $parsed = WebhookParser::parse($payload);
    /** @var IncomingMessage $msg */
    $msg = $parsed['data'];

    expect($msg->type)->toBe('order');
    expect($msg->isOrder())->toBeTrue();
    expect($msg->body)->toBe('Camisa Social');

    $order = $msg->order();
    expect($order)->not->toBeNull();
    expect($order->orderId)->toBe('ORD_1234');
    expect($order->totalAmount)->toBe(199.9);
    expect($order->currency)->toBe('BRL');
});

test('parses contact and contacts_array messages correctly', function () {
    $singlePayload = [
        'event'     => 'message',
        'device_id' => '628123456789@s.whatsapp.net',
        'payload'   => [
            'id'      => 'CONTACT_MSG_1',
            'chat_id' => '5511999998888@s.whatsapp.net',
            'contact' => [
                'displayName'  => 'Ana Silva',
                'phone_number' => '+5511988881111',
            ],
        ],
    ];

    $parsedSingle = WebhookParser::parse($singlePayload);
    /** @var IncomingMessage $msgSingle */
    $msgSingle = $parsedSingle['data'];

    expect($msgSingle->type)->toBe('contact');
    expect($msgSingle->isContact())->toBeTrue();
    expect($msgSingle->contact())->not->toBeNull();
    expect($msgSingle->contact()->name)->toBe('Ana Silva');
    expect($msgSingle->contact()->phone())->toBe('+5511988881111');
    expect($msgSingle->contacts())->toHaveCount(1);

    $arrayPayload = [
        'event'     => 'message',
        'device_id' => '628123456789@s.whatsapp.net',
        'payload'   => [
            'id'             => 'CONTACTS_MSG_2',
            'chat_id'        => '5511999998888@s.whatsapp.net',
            'contacts_array' => [
                ['displayName' => 'Contato 1', 'phone_number' => '+5511988881111'],
                ['displayName' => 'Contato 2', 'phone_number' => '+5511988882222'],
            ],
        ],
    ];

    $parsedArray = WebhookParser::parse($arrayPayload);
    /** @var IncomingMessage $msgArray */
    $msgArray = $parsedArray['data'];

    expect($msgArray->type)->toBe('contacts_array');
    expect($msgArray->isContact())->toBeTrue();
    expect($msgArray->contacts())->toHaveCount(2);
    expect($msgArray->contact()->name)->toBe('Contato 1');
});

test('parses video_note message correctly', function () {
    $payload = [
        'event'     => 'message',
        'device_id' => '628123456789@s.whatsapp.net',
        'payload'   => [
            'id'         => 'PTV_MSG_1',
            'chat_id'    => '5511999998888@s.whatsapp.net',
            'video_note' => [
                'url' => 'https://mmg.whatsapp.net/v/ptv.mp4',
            ],
        ],
    ];

    $parsed = WebhookParser::parse($payload);
    /** @var IncomingMessage $msg */
    $msg = $parsed['data'];

    expect($msg->type)->toBe('video_note');
    expect($msg->isVideoNote())->toBeTrue();
    expect($msg->isMedia())->toBeTrue();
});

test('parses label and newsletter events correctly', function () {
    expect(Event::tryFromValue('label.edit'))->toBe(Event::LabelEdit);
    expect(Event::tryFromValue('label.association'))->toBe(Event::LabelAssociation);
    expect(Event::tryFromValue('newsletter.joined'))->toBe(Event::NewsletterJoined);
    expect(Event::tryFromValue('newsletter.left'))->toBe(Event::NewsletterLeft);
    expect(Event::tryFromValue('newsletter.message'))->toBe(Event::NewsletterMessage);
    expect(Event::tryFromValue('newsletter.mute'))->toBe(Event::NewsletterMute);
});
