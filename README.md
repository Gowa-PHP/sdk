# gowa-php (SDK for GOWA)

Pure PHP SDK for integration with the **go-whatsapp-web-multidevice** ([GOWA](https://github.com/aldinokemal/go-whatsapp-web-multidevice)) server, enabling device pairing, sending and receiving messages, and media handling via WhatsApp Web.

> 🇧🇷 Para ler a documentação em Português, acesse [README.pt.md](README.pt.md).

## Installation

```bash
composer require gowa/gowa-php
```

## Requirements

- PHP >= 8.2
- `json` and `hash` extensions
- PSR-18 compliant HTTP Client or Guzzle Client

## Usage Example

### 1. Initialize Configuration and Client

```php
use Gowa\Sdk\Config;
use Gowa\Sdk\GowaClient;

$config = new Config(
    baseUrl: 'https://gowa.yourcompany.com',
    username: 'admin',
    password: 'secretpassword',
    timeout: 15
);

$client = new GowaClient($config);
```

### 2. Device Pairing (QR Code or Pairing Code)

```php
// Register device and webhook
$device = $client->createDevice(
    deviceId: 'my-instance-uuid',
    webhookUrl: 'https://myapi.com/webhooks/gowa/my-instance-uuid',
    webhookSecret: 'my_hmac_secret_48_chars',
    events: ['message', 'message.ack', 'message.reaction']
);

// Start pairing via QR Code
$pairing = $client->startQrPairing('my-instance-uuid');
echo $pairing->qrLink; // QR Code URL

// Or request 8-digit code for manual typing on phone
$codePairing = $client->startCodePairing('my-instance-uuid', '5511999998888');
echo $codePairing->pairCode; // e.g. ABCD-1234
```

### 3. Sending Messages and Media

```php
use Gowa\Sdk\Dto\MediaType;
use Gowa\Sdk\Dto\MediaUpload;
use Gowa\Sdk\Dto\MediaPayload;

// Send text
$client->sendText('my-instance-uuid', '5511999998888', 'Hello! Message sent via gowa-php SDK.');

// Send voice note (PTT) from external URL
$upload = MediaUpload::fromUrl('https://mycompany.com/storage/voicenote.m4a');
$media = new MediaPayload(type: MediaType::Audio, upload: $upload, voice: true);

$client->sendMedia('my-instance-uuid', '5511999998888', $media);

// Send link preview
$client->sendLink('my-instance-uuid', '5511999998888', 'https://fazz.ai', 'Check our website');

// Send interactive poll
$client->sendPoll('my-instance-uuid', '5511999998888', 'What is your preferred time?', ['Morning', 'Afternoon', 'Evening']);
```

### 4. Webhook Verification (HMAC SHA-256)

```php
use Gowa\Sdk\Security\WebhookSignature;

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$secret = 'my_hmac_secret_48_chars';

if (!WebhookSignature::verify($payload, $signature, $secret)) {
    http_response_code(401);
    exit('Invalid signature');
}
```

## Running Tests (Pest PHP)

```bash
vendor/bin/pest
```

## License

MIT
