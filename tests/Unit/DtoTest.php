<?php

declare(strict_types=1);

use Gowa\Sdk\Dto\Avatar;
use Gowa\Sdk\Dto\ContactCard;
use Gowa\Sdk\Dto\Device;
use Gowa\Sdk\Dto\EventPayload;
use Gowa\Sdk\Dto\LiveLocationPayload;
use Gowa\Sdk\Dto\LocationPayload;
use Gowa\Sdk\Dto\OrderPayload;
use Gowa\Sdk\Dto\Pairing;
use Gowa\Sdk\Dto\PollPayload;

test('device dto parses response correctly', function () {
    $deviceData = [
        'code'    => 'SUCCESS',
        'results' => [
            'id'     => 'device-uuid-123',
            'name'   => 'Vendas Matriz',
            'status' => 'logged_in',
            'phone'  => '5511999998888',
            'jid'    => '5511999998888@s.whatsapp.net',
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

test('location payload parses from array with protobuf or standard keys', function () {
    $protobuf = LocationPayload::fromArray([
        'degreesLatitude'  => -23.55052,
        'degreesLongitude' => -46.633308,
    ]);
    expect($protobuf)->not->toBeNull();
    expect($protobuf->latitude)->toBe(-23.55052);
    expect($protobuf->longitude)->toBe(-46.633308);

    $standard = LocationPayload::fromArray([
        'latitude'  => '-23.55052',
        'longitude' => '-46.633308',
    ]);
    expect($standard)->not->toBeNull();
    expect($standard->latitude)->toBe(-23.55052);
    expect($standard->longitude)->toBe(-46.633308);

    $short = LocationPayload::fromArray([
        'lat' => -23.55052,
        'lng' => -46.633308,
    ]);
    expect($short)->not->toBeNull();
    expect($short->latitude)->toBe(-23.55052);
    expect($short->longitude)->toBe(-46.633308);

    expect(LocationPayload::fromArray([]))->toBeNull();
    expect(LocationPayload::fromArray(['latitude' => 'invalid', 'longitude' => -46.633308]))->toBeNull();
    expect(LocationPayload::fromArray(['latitude' => 91, 'longitude' => 0]))->toBeNull();
    expect(LocationPayload::fromArray(['latitude' => -90.1, 'longitude' => 0]))->toBeNull();
    expect(LocationPayload::fromArray(['latitude' => 0, 'longitude' => 181]))->toBeNull();
    expect(LocationPayload::fromArray(['latitude' => 0, 'longitude' => -180.1]))->toBeNull();
    expect(LocationPayload::fromArray(['latitude' => INF, 'longitude' => 0]))->toBeNull();
});

test('live location payload parses from array correctly', function () {
    $live = LiveLocationPayload::fromArray([
        'degreesLatitude'                   => -23.55052,
        'degreesLongitude'                  => -46.633308,
        'accuracyInMeters'                  => 15,
        'speedInMps'                        => 1.2,
        'degreesClockwiseFromMagneticNorth' => 180,
        'caption'                           => 'A caminho!',
        'sequenceNumber'                    => 1,
        'timeOffset'                        => 0,
    ]);

    expect($live)->not->toBeNull();
    expect($live->latitude)->toBe(-23.55052);
    expect($live->longitude)->toBe(-46.633308);
    expect($live->accuracyInMeters)->toBe(15);
    expect($live->speedInMps)->toBe(1.2);
    expect($live->degreesClockwiseFromMagneticNorth)->toBe(180);
    expect($live->caption)->toBe('A caminho!');
    expect($live->sequenceNumber)->toBe(1);
    expect($live->timeOffset)->toBe(0);

    $loc = $live->toLocationPayload();
    expect($loc)->toBeInstanceOf(LocationPayload::class);
    expect($loc->latitude)->toBe(-23.55052);
    expect($loc->longitude)->toBe(-46.633308);

    $snakeCase = LiveLocationPayload::fromArray([
        'latitude'                              => -23.55052,
        'longitude'                             => -46.633308,
        'accuracy_in_meters'                    => '20',
        'speed_in_mps'                          => '2.5',
        'degrees_clockwise_from_magnetic_north' => '90',
        'sequence_number'                       => '2',
        'time_offset'                           => '10',
    ]);

    expect($snakeCase)->not->toBeNull();
    expect($snakeCase->accuracyInMeters)->toBe(20);
    expect($snakeCase->speedInMps)->toBe(2.5);
    expect($snakeCase->degreesClockwiseFromMagneticNorth)->toBe(90);
    expect($snakeCase->sequenceNumber)->toBe(2);
    expect($snakeCase->timeOffset)->toBe(10);
    expect($snakeCase->caption)->toBeNull();

    expect(LiveLocationPayload::fromArray([]))->toBeNull();
    expect(LiveLocationPayload::fromArray(['latitude' => 91, 'longitude' => 0]))->toBeNull();
    expect(LiveLocationPayload::fromArray(['latitude' => -91, 'longitude' => 0]))->toBeNull();
    expect(LiveLocationPayload::fromArray(['latitude' => 0, 'longitude' => 181]))->toBeNull();
    expect(LiveLocationPayload::fromArray(['latitude' => 0, 'longitude' => -181]))->toBeNull();
    expect(LiveLocationPayload::fromArray(['latitude' => INF, 'longitude' => 0]))->toBeNull();
});

test('poll payload parses from array correctly', function () {
    $creation = PollPayload::fromArray([
        'type'     => 'creation',
        'poll_id'  => 'POLL-1',
        'question' => 'Almoço hoje?',
        'options'  => [
            ['name' => 'Pizza', 'hash' => 'hash_pizza'],
            ['name' => 'Sushi', 'hash' => 'hash_sushi'],
        ],
        'selectable_options_count' => 1,
    ]);

    expect($creation)->not->toBeNull();
    expect($creation->isCreation())->toBeTrue();
    expect($creation->isVote())->toBeFalse();
    expect($creation->pollId)->toBe('POLL-1');
    expect($creation->question)->toBe('Almoço hoje?');
    expect($creation->options)->toHaveCount(2);
    expect($creation->selectableOptionsCount)->toBe(1);

    $vote = PollPayload::fromArray([
        'type'                   => 'vote',
        'poll_id'                => 'POLL-1',
        'question'               => 'Almoço hoje?',
        'selected_options'       => ['Sushi'],
        'selected_option_hashes' => ['hash_sushi'],
        'resolution_status'      => 'resolved',
    ]);

    expect($vote)->not->toBeNull();
    expect($vote->isVote())->toBeTrue();
    expect($vote->isCreation())->toBeFalse();
    expect($vote->isResolved())->toBeTrue();
    expect($vote->selectedOptions)->toBe(['Sushi']);
    expect($vote->selectedOptionHashes)->toBe(['hash_sushi']);

    expect(PollPayload::fromArray([]))->toBeNull();
    expect(PollPayload::fromArray(['question' => 123]))->toBeNull();
    expect(PollPayload::fromArray(['poll_id' => []]))->toBeNull();

    $voteWithHashesOnly = PollPayload::fromArray([
        'selected_option_hashes' => ['hash_sushi'],
    ]);
    expect($voteWithHashesOnly)->not->toBeNull();
    expect($voteWithHashesOnly->selectedOptionHashes)->toBe(['hash_sushi']);
});

test('event payload parses from array correctly', function () {
    $event = EventPayload::fromArray([
        'name'        => 'Reunião de Planejamento',
        'description' => 'Discutir metas do trimestre',
        'start_time'  => 1757599200,
        'end_time'    => 1757602800,
        'is_canceled' => false,
        'call_link'   => 'https://call.whatsapp.com/video/123456',
        'location'    => [
            'latitude'  => -23.55052,
            'longitude' => -46.633308,
        ],
    ]);

    expect($event)->not->toBeNull();
    expect($event->name)->toBe('Reunião de Planejamento');
    expect($event->description)->toBe('Discutir metas do trimestre');
    expect($event->startTime)->toBe(1757599200);
    expect($event->endTime)->toBe(1757602800);
    expect($event->isCanceled)->toBeFalse();
    expect($event->callLink)->toBe('https://call.whatsapp.com/video/123456');
    expect($event->location)->toBeInstanceOf(LocationPayload::class);
    expect($event->location->latitude)->toBe(-23.55052);

    $stringFalse = EventPayload::fromArray([
        'name'        => 'Reunião',
        'is_canceled' => 'false',
    ]);
    expect($stringFalse->isCanceled)->toBeFalse();

    $stringTrue = EventPayload::fromArray([
        'name'        => 'Reunião Cancelada',
        'is_canceled' => 'true',
    ]);
    expect($stringTrue->isCanceled)->toBeTrue();

    expect(EventPayload::fromArray([]))->toBeNull();
});

test('order payload parses from array correctly', function () {
    $order = OrderPayload::fromArray([
        'order_id'            => 'ORD_999',
        'order_title'         => 'Pedido de Roupas',
        'item_count'          => 3,
        'total_amount_1000'   => 150500, // R$ 150.50
        'total_currency_code' => 'BRL',
        'seller_jid'          => '5511999998888@s.whatsapp.net',
        'message'             => 'Obrigado pela compra!',
    ]);

    expect($order)->not->toBeNull();
    expect($order->orderId)->toBe('ORD_999');
    expect($order->title)->toBe('Pedido de Roupas');
    expect($order->itemCount)->toBe(3);
    expect($order->totalAmount)->toBe(150.5);
    expect($order->currency)->toBe('BRL');
    expect($order->sellerJid)->toBe('5511999998888@s.whatsapp.net');

    expect(OrderPayload::fromArray([]))->toBeNull();
    expect(OrderPayload::fromArray(['order_id' => []]))->toBeNull();
    expect(OrderPayload::fromArray(['order_title' => []]))->toBeNull();
});

test('contact card parses from array correctly', function () {
    $single = ContactCard::fromArray([
        'displayName'  => 'Carlos Souza',
        'phone_number' => '+5511977776666',
        'vcard'        => 'BEGIN:VCARD...',
    ]);

    expect($single)->not->toBeNull();
    expect($single->name)->toBe('Carlos Souza');
    expect($single->phone())->toBe('+5511977776666');
    expect($single->vcard)->toBe('BEGIN:VCARD...');

    $filteredPhones = ContactCard::fromArray([
        'name'   => 'Carlos Souza',
        'phones' => [
            ['invalid' => 'missing phone key'],
            ['phone' => ''],
            ['phone' => '+5511977778888'],
        ],
    ]);
    expect($filteredPhones)->not->toBeNull();
    expect($filteredPhones->phones)->toBe([['phone' => '+5511977778888']]);
    expect($filteredPhones->phone())->toBe('+5511977778888');

    expect(ContactCard::fromArray([]))->toBeNull();
});

test('media payload supports mentions and view once', function () {
    $upload = \Gowa\Sdk\Dto\MediaUpload::fromUrl('https://example.com/test.jpg');
    $payload = new \Gowa\Sdk\Dto\MediaPayload(
        type: \Gowa\Sdk\Dto\MediaType::Image,
        upload: $upload,
        caption: 'Look at @5511999998888',
        voice: false,
        mentions: ['5511999998888', '@everyone'],
        viewOnce: true,
    );

    expect($payload->mentions)->toBe(['5511999998888', '@everyone'])
        ->and($payload->viewOnce)->toBeTrue();
});

test('sent message detects scheduled sends and provides schedule details', function () {
    $immediate = new \Gowa\Sdk\Dto\SentMessage(providerMessageId: 'WAMID-123');
    expect($immediate->providerMessageId)->toBe('WAMID-123')
        ->and($immediate->hasMessageId())->toBeTrue()
        ->and($immediate->scheduleId)->toBeNull()
        ->and($immediate->isScheduled())->toBeFalse();

    $legacyPositional = new \Gowa\Sdk\Dto\SentMessage('WAMID-456', ['results' => ['message_id' => 'WAMID-456']]);
    expect($legacyPositional->providerMessageId)->toBe('WAMID-456')
        ->and($legacyPositional->hasMessageId())->toBeTrue()
        ->and($legacyPositional->raw)->toBe(['results' => ['message_id' => 'WAMID-456']])
        ->and($legacyPositional->scheduleId)->toBeNull()
        ->and($legacyPositional->isScheduled())->toBeFalse();

    $scheduled = new \Gowa\Sdk\Dto\SentMessage(
        scheduleId: 'SCHED-UUID-999',
        scheduledAt: '2026-10-01T09:00:00Z',
    );
    expect($scheduled->providerMessageId)->toBe('')
        ->and($scheduled->hasMessageId())->toBeFalse()
        ->and($scheduled->scheduleId)->toBe('SCHED-UUID-999')
        ->and($scheduled->scheduledAt)->toBe('2026-10-01T09:00:00Z')
        ->and($scheduled->isScheduled())->toBeTrue();
});

test('schedule options converts to array and multipart correctly', function () {
    $options = new \Gowa\Sdk\Dto\ScheduleOptions(
        scheduledAt: '2026-10-01T09:00:00+07:00',
        timezone: 'Asia/Jakarta',
        recurrence: 'weekly',
        weekdays: [1, 3, 5],
        dayOfMonth: 15,
        endAt: '2026-12-31T23:59:59+07:00',
        occurrenceLimit: 10,
    );

    $array = $options->toArray();
    expect($array['scheduled_at'])->toBe('2026-10-01T09:00:00+07:00')
        ->and($array['timezone'])->toBe('Asia/Jakarta')
        ->and($array['recurrence'])->toBe('weekly')
        ->and($array['weekdays'])->toBe([1, 3, 5])
        ->and($array['day_of_month'])->toBe(15)
        ->and($array['end_at'])->toBe('2026-12-31T23:59:59+07:00')
        ->and($array['occurrence_limit'])->toBe(10);

    $multipart = $options->toMultipart();
    expect($multipart)->toContain(['name' => 'scheduled_at', 'contents' => '2026-10-01T09:00:00+07:00'])
        ->and($multipart)->toContain(['name' => 'timezone', 'contents' => 'Asia/Jakarta'])
        ->and($multipart)->toContain(['name' => 'weekdays', 'contents' => '1'])
        ->and($multipart)->toContain(['name' => 'weekdays', 'contents' => '3'])
        ->and($multipart)->toContain(['name' => 'weekdays', 'contents' => '5'])
        ->and($multipart)->toContain(['name' => 'day_of_month', 'contents' => '15'])
        ->and($multipart)->toContain(['name' => 'occurrence_limit', 'contents' => '10']);
});

test('schedule dto parses complete schedule response correctly', function () {
    $data = [
        'id'               => 'sched-123',
        'message_type'     => 'image',
        'phone'            => '5511999998888@s.whatsapp.net',
        'summary'          => 'Relatório mensal',
        'status'           => 'active',
        'scheduled_at'     => '2026-10-01T09:00:00Z',
        'next_run_at'      => '2026-10-01T09:00:00Z',
        'timezone'         => 'America/Sao_Paulo',
        'recurrence'       => 'monthly',
        'day_of_month'     => 1,
        'occurrence_limit' => 12,
        'occurrence_count' => 2,
        'attempts'         => 0,
        'last_run_at'      => '2026-09-01T09:00:00Z',
        'last_message_id'  => 'WAMID-PREV-1',
        'last_error'       => null,
        'created_at'       => '2026-08-01T00:00:00Z',
        'updated_at'       => '2026-09-01T09:00:01Z',
    ];

    $schedule = \Gowa\Sdk\Dto\Schedule::fromArray($data);

    expect($schedule->id)->toBe('sched-123')
        ->and($schedule->messageType)->toBe('image')
        ->and($schedule->phone)->toBe('5511999998888@s.whatsapp.net')
        ->and($schedule->summary)->toBe('Relatório mensal')
        ->and($schedule->status)->toBe(\Gowa\Sdk\Dto\ScheduleStatus::Active)
        ->and($schedule->scheduledAt)->toBe('2026-10-01T09:00:00Z')
        ->and($schedule->nextRunAt)->toBe('2026-10-01T09:00:00Z')
        ->and($schedule->recurrence)->toBe('monthly')
        ->and($schedule->dayOfMonth)->toBe(1)
        ->and($schedule->occurrenceCount)->toBe(2)
        ->and($schedule->lastMessageId)->toBe('WAMID-PREV-1');
});

test('schedule dto parses comma-separated weekdays and guards against empty string numbers', function () {
    $data = [
        'id'               => 'sched-csv',
        'message_type'     => 'text',
        'phone'            => '5511999998888',
        'weekdays'         => '1, 3, 5',
        'day_of_month'     => '',
        'occurrence_limit' => '',
    ];

    $schedule = \Gowa\Sdk\Dto\Schedule::fromArray($data);

    expect($schedule->weekdays)->toBe([1, 3, 5])
        ->and($schedule->dayOfMonth)->toBeNull()
        ->and($schedule->occurrenceLimit)->toBeNull();
});

test('schedule status enum resolves statuses safely with unknown fallback', function () {
    expect(\Gowa\Sdk\Dto\ScheduleStatus::tryFromValue('ACTIVE'))->toBe(\Gowa\Sdk\Dto\ScheduleStatus::Active)
        ->and(\Gowa\Sdk\Dto\ScheduleStatus::tryFromValue('paused'))->toBe(\Gowa\Sdk\Dto\ScheduleStatus::Paused)
        ->and(\Gowa\Sdk\Dto\ScheduleStatus::tryFromValue('COMPLETED'))->toBe(\Gowa\Sdk\Dto\ScheduleStatus::Completed)
        ->and(\Gowa\Sdk\Dto\ScheduleStatus::tryFromValue('failed'))->toBe(\Gowa\Sdk\Dto\ScheduleStatus::Failed)
        ->and(\Gowa\Sdk\Dto\ScheduleStatus::tryFromValue('cancelled'))->toBe(\Gowa\Sdk\Dto\ScheduleStatus::Cancelled)
        ->and(\Gowa\Sdk\Dto\ScheduleStatus::tryFromValue('non_existent_status'))->toBe(\Gowa\Sdk\Dto\ScheduleStatus::Unknown);
});

test('device dto parses phone_number key fallback correctly', function () {
    $device = Device::fromResults([
        'id'           => 'dev-fallback',
        'display_name' => 'Fallback Dev',
        'state'        => 'connected',
        'phone_number' => '5511988887777',
    ]);

    expect($device->phone)->toBe('5511988887777');
});
