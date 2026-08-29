<?php

declare(strict_types=1);

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Gowa\Sdk\Config;
use Gowa\Sdk\GowaClient;

function createMockGowaClient(array $responses, ?Config $config = null): GowaClient
{
    $mockHandler = new MockHandler($responses);
    $handlerStack = HandlerStack::create($mockHandler);
    $guzzleClient = new GuzzleClient(['handler' => $handlerStack]);

    $config = $config ?? new Config(
        baseUrl: 'https://gowa.example.com',
        username: 'admin',
        password: 'secretpassword',
    );

    return new GowaClient($config, $guzzleClient);
}
