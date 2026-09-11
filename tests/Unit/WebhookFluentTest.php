<?php

declare(strict_types=1);

use Gowa\Sdk\Dto\ContactCard;
use Gowa\Sdk\Dto\EventPayload;
use Gowa\Sdk\Dto\LiveLocationPayload;
use Gowa\Sdk\Dto\OrderPayload;
use Gowa\Sdk\Dto\PollPayload;
use Gowa\Sdk\Webhook\Dto\IncomingAck;
use Gowa\Sdk\Webhook\Dto\IncomingMessage;
use Gowa\Sdk\Webhook\Dto\IncomingReaction;
use Gowa\Sdk\Webhook\Event;
use Gowa\Sdk\Webhook\MessageType;
use Gowa\Sdk\Webhook\WebhookEvent;
use Gowa\Sdk\Webhook\WebhookParser;

test('message type enum resolves known and unknown types safely', function () {
    expect(MessageType::tryFromValue('text'))->toBe(MessageType::Text);
    expect(MessageType::tryFromValue('LIVE_LOCATION'))->toBe(MessageType::LiveLocation);
    expect(MessageType::tryFromValue('poll'))->toBe(MessageType::Poll);
    expect(MessageType::tryFromValue('something_completely_new'))->toBe(MessageType::Unknown);
});

test('webhook event fluent routing dispatches matching event and prevents duplicate execution', function () {
    $payload = [
        'event'     => 'message',
        'device_id' => '628123456789@s.whatsapp.net',
        'payload'   => [
            'id'      => 'MSG_1',
            'chat_id' => '5511999998888@s.whatsapp.net',
            'body'    => 'Olá mundo!',
        ],
    ];

    $event = WebhookParser::parse($payload);
    expect($event)->toBeInstanceOf(WebhookEvent::class);
    expect($event->isMessage())->toBeTrue();
    expect($event->message())->toBeInstanceOf(IncomingMessage::class);
    expect($event['event'])->toBe(Event::Message);
    expect($event['event_id'])->toBe('message:MSG_1');
    expect($event->toArray())->toHaveKeys(['event', 'event_id', 'data', 'raw']);

    $handledMessage = false;
    $handledAck = false;
    $handledOtherwise = false;

    $event
        ->onMessage(function (IncomingMessage $msg) use (&$handledMessage) {
            $handledMessage = true;
            expect($msg->body)->toBe('Olá mundo!');
        })
        ->onAck(function (IncomingAck $ack) use (&$handledAck) {
            $handledAck = true;
        })
        ->otherwise(function () use (&$handledOtherwise) {
            $handledOtherwise = true;
        });

    expect($handledMessage)->toBeTrue();
    expect($handledAck)->toBeFalse();
    expect($handledOtherwise)->toBeFalse();
    expect($event->isHandled())->toBeTrue();
});

test('webhook event handles ack, reaction, and unknown events via otherwise', function () {
    $ackPayload = [
        'event'     => 'message.ack',
        'device_id' => '628123456789@s.whatsapp.net',
        'payload'   => [
            'ids'          => ['MSG_100'],
            'chat_id'      => '5511999998888@s.whatsapp.net',
            'receipt_type' => 'READ',
        ],
    ];

    $ackEvent = WebhookParser::parse($ackPayload);
    expect($ackEvent->isAck())->toBeTrue();
    expect($ackEvent->ack())->toBeInstanceOf(IncomingAck::class);

    $calledAck = false;
    $ackEvent->onAck(function (IncomingAck $ack) use (&$calledAck) {
        $calledAck = true;
        expect($ack->receiptType)->toBe('read');
        expect($ack->isRead())->toBeTrue();
    });
    expect($calledAck)->toBeTrue();

    $reactionPayload = [
        'event'     => 'message.reaction',
        'device_id' => '628123456789@s.whatsapp.net',
        'payload'   => [
            'id'                 => 'REACT_1',
            'chat_id'            => '5511999998888@s.whatsapp.net',
            'reaction'           => '👍',
            'reacted_message_id' => 'MSG_1',
        ],
    ];

    $reactionEvent = WebhookParser::parse($reactionPayload);
    expect($reactionEvent->isReaction())->toBeTrue();
    expect($reactionEvent->reaction())->toBeInstanceOf(IncomingReaction::class);

    $calledReaction = false;
    $reactionEvent->onReaction(function (IncomingReaction $reaction) use (&$calledReaction) {
        $calledReaction = true;
        expect($reaction->emoji)->toBe('👍');
    });
    expect($calledReaction)->toBeTrue();

    $unknownPayload = [
        'event'   => 'unsupported.custom.event',
        'payload' => ['foo' => 'bar'],
    ];

    $unknownEvent = WebhookParser::parse($unknownPayload);
    expect($unknownEvent->event)->toBe(Event::Unknown);

    $calledOtherwise = false;
    $unknownEvent
        ->onMessage(fn() => null)
        ->otherwise(function (mixed $data, Event $evt) use (&$calledOtherwise) {
            $calledOtherwise = true;
            expect($evt)->toBe(Event::Unknown);
            expect($data)->toBe(['foo' => 'bar']);
        });

    expect($calledOtherwise)->toBeTrue();
});

test('incoming message fluent handlers dispatch typed payloads correctly', function () {
    // 1. Live Location
    $liveLocPayload = [
        'event'     => 'message',
        'device_id' => '628123456789@s.whatsapp.net',
        'payload'   => [
            'id'            => 'LIVE_1',
            'chat_id'       => '5511999998888@s.whatsapp.net',
            'live_location' => [
                'latitude'  => -23.55052,
                'longitude' => -46.633308,
            ],
        ],
    ];

    $parsedLive = WebhookParser::parse($liveLocPayload);
    $msgLive = $parsedLive->message();
    expect($msgLive)->not->toBeNull();
    expect($msgLive->messageType())->toBe(MessageType::LiveLocation);

    $calledLive = false;
    $msgLive->whenLiveLocation(function (LiveLocationPayload $loc) use (&$calledLive) {
        $calledLive = true;
        expect($loc->latitude)->toBe(-23.55052);
    });
    expect($calledLive)->toBeTrue();

    // 2. Poll
    $pollPayload = [
        'event'     => 'message',
        'device_id' => '628123456789@s.whatsapp.net',
        'payload'   => [
            'id'      => 'POLL_1',
            'chat_id' => '5511999998888@s.whatsapp.net',
            'poll'    => [
                'question' => 'Pizza ou Hambúrguer?',
                'options'  => [['name' => 'Pizza'], ['name' => 'Hambúrguer']],
            ],
        ],
    ];

    $msgPoll = WebhookParser::parse($pollPayload)->message();
    $calledPoll = false;
    $msgPoll->whenPoll(function (PollPayload $poll) use (&$calledPoll) {
        $calledPoll = true;
        expect($poll->question)->toBe('Pizza ou Hambúrguer?');
    });
    expect($calledPoll)->toBeTrue();

    // 3. Event
    $eventPayload = [
        'event'     => 'message',
        'device_id' => '628123456789@s.whatsapp.net',
        'payload'   => [
            'id'            => 'EVT_1',
            'chat_id'       => '5511999998888@s.whatsapp.net',
            'event_message' => [
                'name' => 'Reunião Geral',
            ],
        ],
    ];

    $msgEvent = WebhookParser::parse($eventPayload)->message();
    $calledEvent = false;
    $msgEvent->whenEvent(function (EventPayload $event) use (&$calledEvent) {
        $calledEvent = true;
        expect($event->name)->toBe('Reunião Geral');
    });
    expect($calledEvent)->toBeTrue();

    // 4. Order
    $orderPayload = [
        'event'     => 'message',
        'device_id' => '628123456789@s.whatsapp.net',
        'payload'   => [
            'id'      => 'ORD_1',
            'chat_id' => '5511999998888@s.whatsapp.net',
            'order'   => [
                'order_id'    => 'PEDIDO-99',
                'order_title' => 'Notebook',
            ],
        ],
    ];

    $msgOrder = WebhookParser::parse($orderPayload)->message();
    $calledOrder = false;
    $msgOrder->whenOrder(function (OrderPayload $order) use (&$calledOrder) {
        $calledOrder = true;
        expect($order->orderId)->toBe('PEDIDO-99');
    });
    expect($calledOrder)->toBeTrue();

    // 5. Contact
    $contactPayload = [
        'event'     => 'message',
        'device_id' => '628123456789@s.whatsapp.net',
        'payload'   => [
            'id'      => 'CNT_1',
            'chat_id' => '5511999998888@s.whatsapp.net',
            'contact' => [
                'name'  => 'Ana Silva',
                'phone' => '+5511999991111',
            ],
        ],
    ];

    $msgContact = WebhookParser::parse($contactPayload)->message();
    $calledContact = false;
    $msgContact->whenContact(function (ContactCard $card) use (&$calledContact) {
        $calledContact = true;
        expect($card->name)->toBe('Ana Silva');
    });
    expect($calledContact)->toBeTrue();

    // 6. Text
    $textPayload = [
        'event'     => 'message',
        'device_id' => '628123456789@s.whatsapp.net',
        'payload'   => [
            'id'      => 'TXT_1',
            'chat_id' => '5511999998888@s.whatsapp.net',
            'body'    => 'Mensagem de texto simples',
        ],
    ];

    $msgText = WebhookParser::parse($textPayload)->message();
    $calledText = false;
    $msgText->whenText(function (string $text) use (&$calledText) {
        $calledText = true;
        expect($text)->toBe('Mensagem de texto simples');
    });
    expect($calledText)->toBeTrue();

    // 7. Media
    $imagePayload = [
        'event'     => 'message',
        'device_id' => '628123456789@s.whatsapp.net',
        'payload'   => [
            'id'      => 'IMG_1',
            'chat_id' => '5511999998888@s.whatsapp.net',
            'image'   => [
                'caption' => 'Foto das férias',
                'url'     => 'https://example.com/photo.jpg',
            ],
        ],
    ];

    $msgImage = WebhookParser::parse($imagePayload)->message();
    $calledMedia = false;
    $msgImage->whenMedia(function (array $media) use (&$calledMedia) {
        $calledMedia = true;
        expect($media['caption'])->toBe('Foto das férias');
    });
    expect($calledMedia)->toBeTrue();
});

test('incoming message falls back to otherwise on malformed or unhandled type', function () {
    // Malformed live location (e.g. invalid latitude 999) returns null from DTO,
    // so whenLiveLocation must NOT match and gracefully fall to otherwise
    $malformedPayload = [
        'event'     => 'message',
        'device_id' => '628123456789@s.whatsapp.net',
        'payload'   => [
            'id'            => 'MALFORMED_1',
            'chat_id'       => '5511999998888@s.whatsapp.net',
            'live_location' => [
                'latitude'  => 999, // invalid coordinate
                'longitude' => 0,
            ],
        ],
    ];

    $msg = WebhookParser::parse($malformedPayload)->message();
    expect($msg)->not->toBeNull();

    $calledSpecific = false;
    $calledOtherwise = false;

    $msg
        ->whenLiveLocation(function (LiveLocationPayload $loc) use (&$calledSpecific) {
            $calledSpecific = true;
        })
        ->otherwise(function (IncomingMessage $fallbackMsg) use (&$calledOtherwise) {
            $calledOtherwise = true;
            expect($fallbackMsg->id)->toBe('MALFORMED_1');
        });

    expect($calledSpecific)->toBeFalse();
    expect($calledOtherwise)->toBeTrue();
});
