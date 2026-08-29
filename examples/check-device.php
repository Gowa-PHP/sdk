<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Gowa\Sdk\Config;
use Gowa\Sdk\GowaClient;

$requiredVariables = [
    'GOWA_BASE_URL',
    'GOWA_USERNAME',
    'GOWA_PASSWORD',
    'GOWA_DEVICE_ID',
];

$values = [];
foreach ($requiredVariables as $variable) {
    $value = getenv($variable);
    if ($value === false || $value === '') {
        fwrite(STDERR, "Missing required environment variable: {$variable}\n");
        exit(1);
    }

    $values[$variable] = $value;
}

$config = new Config(
    baseUrl: $values['GOWA_BASE_URL'],
    username: $values['GOWA_USERNAME'],
    password: $values['GOWA_PASSWORD'],
);

try {
    $client = new GowaClient($config);
    $device = $client->device($values['GOWA_DEVICE_ID']);
} catch (Throwable $exception) {
    fwrite(STDERR, "Unable to query the GOWA endpoint: {$exception->getMessage()}\n");
    exit(1);
}

if ($device === null) {
    fwrite(STDERR, "The endpoint responded, but device '{$values['GOWA_DEVICE_ID']}' was not found.\n");
    exit(2);
}

echo "Connected successfully. Device details:\n";
var_dump($device);
