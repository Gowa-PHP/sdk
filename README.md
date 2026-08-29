# gowa-php (PHP SDK for GOWA)

Pure PHP SDK for integration with the **go-whatsapp-web-multidevice** ([GOWA](https://github.com/aldinokemal/go-whatsapp-web-multidevice)) server, enabling device pairing, sending and receiving messages, media handling, interactive polls, link previews, and webhook parsing via WhatsApp Web.

> 🇧🇷 Para ler a documentação em Português, acesse [README.pt.md](README.pt.md).

## Installation

```bash
composer require aguinaldotupy/gowa-php
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

### 2. Device Pairing (QR Code or 8-Digit Code)

```php
// Register device and webhook
$device = $client->createDevice(
    deviceId: 'my-instance-uuid',
    webhookUrl: 'https://myapi.com/webhooks/gowa/my-instance-uuid',
    webhookSecret: 'my_hmac_secret_48_chars',
    events: ['message', 'message.ack', 'message.reaction', 'message.edited', 'message.revoked']
);

// Start pairing via QR Code
$pairing = $client->startQrPairing('my-instance-uuid');
echo $pairing->qrLink; // QR Code URL

// Or request 8-digit code for manual typing on phone
$codePairing = $client->startCodePairing('my-instance-uuid', '5511999998888');
echo $codePairing->pairCode; // e.g. ABCD-1234
```

### 3. Sending Messages and Media

#### Text, Links & Polls
```php
// Send text
$client->sendText('my-instance-uuid', '5511999998888', 'Hello! Message sent via gowa-php SDK.');

// Send URL link with preview
$client->sendLink('my-instance-uuid', '5511999998888', 'https://fazz.ai', 'Check our website');

// Send interactive poll
$client->sendPoll('my-instance-uuid', '5511999998888', 'What is your preferred time?', ['Morning', 'Afternoon', 'Evening']);
```

#### Media Uploads (Files, URLs, Streams & Voice Notes)
```php
use Gowa\Sdk\Dto\MediaType;
use Gowa\Sdk\Dto\MediaUpload;
use Gowa\Sdk\Dto\MediaPayload;

// Send voice note (PTT) from external URL
$upload = MediaUpload::fromUrl('https://mycompany.com/storage/voicenote.m4a');
$media = new MediaPayload(type: MediaType::Audio, upload: $upload, voice: true);
$client->sendMedia('my-instance-uuid', '5511999998888', $media);

// Send local document
$docUpload = MediaUpload::fromPath('/path/to/invoice.pdf');
$docMedia = new MediaPayload(type: MediaType::Document, upload: $docUpload);
$client->sendMedia('my-instance-uuid', '5511999998888', $docMedia);
```

#### Message Actions (Forward, Edit, Revoke, Reactions, Star)
```php
// Forward message
$client->forwardMessage('my-instance-uuid', '5511999998888', 'WAMID_ORIGINAL_123');

// Edit sent text
$client->editMessage('my-instance-uuid', '5511999998888', 'WAMID_ORIGINAL_123', 'Updated message text');

// Send emoji reaction
$client->sendReaction('my-instance-uuid', '5511999998888', 'WAMID_ORIGINAL_123', '👍');

// Revoke (Delete for everyone)
$client->revokeMessage('my-instance-uuid', '5511999998888', 'WAMID_ORIGINAL_123');

// Star or unstar message
$client->starMessage('my-instance-uuid', '5511999998888', 'WAMID_ORIGINAL_123', true);
```

### 4. Webhook Verification & Event Parsing

```php
use Gowa\Sdk\Security\WebhookSignature;
use Gowa\Sdk\Webhook\WebhookParser;
use Gowa\Sdk\Webhook\Event;
use Gowa\Sdk\Webhook\Dto\IncomingMessage;
use Gowa\Sdk\Webhook\Dto\IncomingAck;

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$secret = 'my_hmac_secret_48_chars';

// 1. Verify HMAC SHA-256 signature
if (!WebhookSignature::verify($payload, $signature, $secret)) {
    http_response_code(401);
    exit('Invalid signature');
}

// 2. Parse incoming webhook payload
$parsed = WebhookParser::parse($payload);

match ($parsed['event']) {
    Event::Message => /** @var IncomingMessage $msg */ $msg = $parsed['data'],
    Event::MessageAck => /** @var IncomingAck $ack */ $ack = $parsed['data'],
    default => null,
};
```

## Available Features Summary

| Feature | Method | Endpoint |
|---|---|---|
| Text Message | `sendText()` | `POST /send/message` |
| Image | `sendMedia()` | `POST /send/image` |
| Video | `sendMedia()` | `POST /send/video` |
| Audio / PTT Voice Note | `sendMedia()` (voice: true) | `POST /send/audio` |
| Document / File | `sendMedia()` | `POST /send/file` |
| WebP Sticker | `sendSticker()` | `POST /send/sticker` |
| Location | `sendLocation()` | `POST /send/location` |
| Contact Card | `sendContacts()` | `POST /send/contact` |
| URL Link Preview | `sendLink()` | `POST /send/link` |
| Interactive Poll | `sendPoll()` | `POST /send/poll` |
| Emoji Reaction | `sendReaction()` | `POST /message/:id/reaction` |
| Forward Message | `forwardMessage()` | `POST /message/:id/forward` |
| Edit Message | `editMessage()` | `POST /message/:id/update` |
| Revoke Message | `revokeMessage()` | `POST /message/:id/revoke` |
| Delete Message (Local) | `deleteMessage()` | `POST /message/:id/delete` |
| Star / Unstar Message | `starMessage()` | `POST /message/:id/star` |
| Mark Audio Played | `markPlayed()` | `POST /message/:id/played` |
| Mark Read / Typing | `markRead()` | `POST /message/:id/read` |

## Running Tests (Pest PHP)

```bash
vendor/bin/pest
```

## License

MIT
