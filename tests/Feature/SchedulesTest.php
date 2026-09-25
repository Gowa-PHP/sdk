<?php

declare(strict_types=1);

use Gowa\Sdk\Config;
use Gowa\Sdk\Dto\Schedule;
use Gowa\Sdk\Dto\ScheduleStatus;
use Gowa\Sdk\GowaClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

test('listSchedules requests /send/schedules with filters and pagination', function () {
    $mockHandler = new MockHandler([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'message' => 'Schedules fetched',
            'results' => [
                'data' => [
                    [
                        'id'           => 'sched-1',
                        'message_type' => 'text',
                        'phone'        => '5511999998888@s.whatsapp.net',
                        'summary'      => 'Aviso semanal',
                        'status'       => 'active',
                        'scheduled_at' => '2026-10-01T09:00:00Z',
                    ],
                    [
                        'id'           => 'sched-2',
                        'message_type' => 'image',
                        'phone'        => '5511888887777@s.whatsapp.net',
                        'summary'      => 'Foto promo',
                        'status'       => 'paused',
                        'scheduled_at' => '2026-10-02T10:00:00Z',
                    ],
                ],
                'pagination' => [
                    'limit'  => 20,
                    'offset' => 10,
                    'total'  => 42,
                ],
            ],
        ])),
    ]);

    $config = new Config(
        baseUrl: 'https://gowa.example.com',
        username: 'admin',
        password: 'secretpassword',
    );
    $client = new GowaClient($config, handler: $mockHandler);

    $response = $client->listSchedules(
        deviceId: 'dev-1',
        status: ScheduleStatus::Active,
        messageType: 'text',
        search: 'Aviso',
        limit: 20,
        offset: 10,
    );

    expect($response['data'])->toHaveCount(2)
        ->and($response['data'][0])->toBeInstanceOf(Schedule::class)
        ->and($response['data'][0]->id)->toBe('sched-1')
        ->and($response['data'][0]->status)->toBe(ScheduleStatus::Active)
        ->and($response['data'][1]->id)->toBe('sched-2')
        ->and($response['data'][1]->status)->toBe(ScheduleStatus::Paused)
        ->and($response['pagination']['limit'])->toBe(20)
        ->and($response['pagination']['offset'])->toBe(10)
        ->and($response['pagination']['total'])->toBe(42);

    $request = $mockHandler->getLastRequest();
    expect($request->getMethod())->toBe('GET')
        ->and($request->getUri()->getPath())->toBe('/send/schedules')
        ->and($request->getHeaderLine('X-Device-Id'))->toBe('dev-1');

    $query = $request->getUri()->getQuery();
    expect($query)->toContain('limit=20')
        ->and($query)->toContain('offset=10')
        ->and($query)->toContain('status=active')
        ->and($query)->toContain('message_type=text')
        ->and($query)->toContain('search=Aviso');
});

test('getSchedule retrieves single schedule by id', function () {
    $mockHandler = new MockHandler([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [
                'id'           => 'sched-single',
                'message_type' => 'document',
                'phone'        => '5511999998888@s.whatsapp.net',
                'summary'      => 'relatorio.pdf',
                'status'       => 'completed',
            ],
        ])),
    ]);

    $config = new Config(
        baseUrl: 'https://gowa.example.com',
        username: 'admin',
        password: 'secretpassword',
    );
    $client = new GowaClient($config, handler: $mockHandler);

    $schedule = $client->getSchedule('dev-1', 'sched-single');

    expect($schedule)->toBeInstanceOf(Schedule::class)
        ->and($schedule->id)->toBe('sched-single')
        ->and($schedule->status)->toBe(ScheduleStatus::Completed);

    $request = $mockHandler->getLastRequest();
    expect($request->getMethod())->toBe('GET')
        ->and($request->getUri()->getPath())->toBe('/send/schedules/sched-single')
        ->and($request->getHeaderLine('X-Device-Id'))->toBe('dev-1');
});

test('pauseSchedule, resumeSchedule and cancelSchedule execute corresponding POST endpoints', function () {
    $mockHandler = new MockHandler([
        new Response(200, [], json_encode(['code' => 'SUCCESS', 'message' => 'Paused'])),
        new Response(200, [], json_encode(['code' => 'SUCCESS', 'message' => 'Resumed'])),
        new Response(200, [], json_encode(['code' => 'SUCCESS', 'message' => 'Cancelled'])),
    ]);

    $config = new Config(
        baseUrl: 'https://gowa.example.com',
        username: 'admin',
        password: 'secretpassword',
    );
    $client = new GowaClient($config, handler: $mockHandler);

    $client->pauseSchedule('dev-1', 'sched-1');
    $req1 = $mockHandler->getLastRequest();
    expect($req1->getMethod())->toBe('POST')
        ->and($req1->getUri()->getPath())->toBe('/send/schedules/sched-1/pause')
        ->and($req1->getHeaderLine('X-Device-Id'))->toBe('dev-1');

    $client->resumeSchedule('dev-1', 'sched-1');
    $req2 = $mockHandler->getLastRequest();
    expect($req2->getMethod())->toBe('POST')
        ->and($req2->getUri()->getPath())->toBe('/send/schedules/sched-1/resume')
        ->and($req2->getHeaderLine('X-Device-Id'))->toBe('dev-1');

    $client->cancelSchedule('dev-1', 'sched-1');
    $req3 = $mockHandler->getLastRequest();
    expect($req3->getMethod())->toBe('POST')
        ->and($req3->getUri()->getPath())->toBe('/send/schedules/sched-1/cancel')
        ->and($req3->getHeaderLine('X-Device-Id'))->toBe('dev-1');
});

test('listSchedules sends status=unknown when ScheduleStatus::Unknown is provided', function () {
    $mockHandler = new MockHandler([
        new Response(200, [], json_encode([
            'code'    => 'SUCCESS',
            'results' => [
                'data'       => [],
                'pagination' => ['limit' => 25, 'offset' => 0, 'total' => 0],
            ],
        ])),
    ]);

    $config = new Config(
        baseUrl: 'https://gowa.example.com',
        username: 'admin',
        password: 'secretpassword',
    );
    $client = new GowaClient($config, handler: $mockHandler);

    $client->listSchedules('dev-1', status: ScheduleStatus::Unknown);

    $request = $mockHandler->getLastRequest();
    expect($request->getUri()->getQuery())->toContain('status=unknown');
});
