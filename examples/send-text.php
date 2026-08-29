<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Gowa\Sdk\Config;
use Gowa\Sdk\GowaClient;

function prompt(string $label): string
{
    fwrite(STDOUT, $label);
    $value = fgets(STDIN);

    return $value === false ? '' : trim($value);
}

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

$recipient = getenv('GOWA_RECIPIENT') ?: prompt('Recipient number (international format): ');
$text = getenv('GOWA_TEST_MESSAGE') ?: prompt('Message text: ');

if ($recipient === '' || $text === '') {
    fwrite(STDERR, "Recipient number and message text are required.\n");
    exit(1);
}

if (getenv('GOWA_SEND_MESSAGE') !== '1') {
    $confirmation = prompt("This sends a real message. Type SEND to continue: ");
    if ($confirmation !== 'SEND') {
        fwrite(STDERR, "Message was not sent.\n");
        exit(1);
    }
}

$config = new Config(
    baseUrl: $values['GOWA_BASE_URL'],
    username: $values['GOWA_USERNAME'],
    password: $values['GOWA_PASSWORD'],
);

try {
    $client = new GowaClient($config);
    $message = $client->sendText(
        $values['GOWA_DEVICE_ID'],
        $recipient,
        $text,
    );
} catch (Throwable $exception) {
    fwrite(STDERR, "Unable to send the test message: {$exception->getMessage()}\n");
    exit(1);
}

echo "Message sent successfully. Response details:\n";
var_dump($message);
