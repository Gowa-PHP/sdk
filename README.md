<div align="center">
  <img src="art/banner.png" alt="gowa-php Banner" width="100%" max-width="800">

  # gowa-php

  **Pure PHP SDK for GOWA (go-whatsapp-web-multidevice) & WhatsApp Web, powered by whatsmeow**

  [![Latest Version](https://img.shields.io/packagist/v/gowa-php/sdk.svg?style=flat-square)](https://packagist.org/packages/gowa-php/sdk)
  [![Total Downloads](https://img.shields.io/packagist/dt/gowa-php/sdk.svg?style=flat-square)](https://packagist.org/packages/gowa-php/sdk)
  [![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)
  [![PHP Version](https://img.shields.io/badge/PHP-%3E%3D%208.2-777BB4.svg?style=flat-square)](https://php.net)

</div>

---

> 🇧🇷 Para ler a documentação em Português, acesse [README.pt.md](README.pt.md).

---

## ⚡ Acknowledgments & Dependencies

This SDK interacts with the Go backend ecosystem created by the open-source community:

- **[whatsmeow](https://go.mau.fi/whatsmeow)** — The underlying Go library created by [Tulir Asokan](https://github.com/tulir) that reverse-engineers the WhatsApp Web Multi-Device WebSocket protocol and Signal encryption.
- **[go-whatsapp-web-multidevice (GOWA)](https://github.com/aldinokemal/go-whatsapp-web-multidevice)** — The lightweight REST API wrapper created by [Aldino Kemal](https://github.com/aldinokemal) exposing `whatsmeow` over HTTP and Webhooks.
- **[OpenAPI Specification](https://github.com/aldinokemal/go-whatsapp-web-multidevice/blob/main/docs/openapi.yaml)** — Official GOWA OpenAPI 3.0 specification ([View in Swagger Editor](https://editor.swagger.io/?url=https://raw.githubusercontent.com/aldinokemal/go-whatsapp-web-multidevice/main/docs/openapi.yaml)).

---

## Installation

```bash
composer require gowa-php/sdk
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

### Device Management

| Feature | Method | Endpoint |
|---|---|---|
| Register Device & Webhook | `createDevice()` | `POST /devices` |
| Update Webhook Config | `updateWebhook()` | `PATCH /devices/:id/webhook` |
| Start QR Pairing | `startQrPairing()` | `GET /devices/:id/login` |
| Start 8-Digit Code Pairing | `startCodePairing()` | `POST /devices/:id/login/code` |
| Query Device Info & Status | `device()` | `GET /devices/:id` |
| Logout Device | `logout()` | `POST /devices/:id/logout` |

### Messages & Interactions

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
| Revoke Message (Delete for All) | `revokeMessage()` | `POST /message/:id/revoke` |
| Delete Message (Local) | `deleteMessage()` | `POST /message/:id/delete` |
| Star / Unstar Message | `starMessage()` | `POST /message/:id/star`, `POST /message/:id/unstar` |
| Mark Audio Played | `markPlayed()` | `POST /message/:id/played` |
| Mark Read / Typing | `markRead()` | `POST /message/:id/read` |

### Contacts & Media Download

| Feature | Method | Endpoint |
|---|---|---|
| Contact Profile Picture | `avatar()` | `GET /user/avatar` |
| Prepare Media Download | `describeMedia()` | `GET /message/:id/download` |
| Download Decrypted Media | `downloadMedia()` | GET media URL |

## Running Tests (Pest PHP)

```bash
vendor/bin/pest
```

## ⚠️ Disclaimer & Terms of Use

This software is an open-source library created for **educational, research, and testing laboratory purposes**.

- **Third-Party Terms of Service**: Users of this library are solely responsible for complying with WhatsApp's Terms of Service, Meta's Platform Policies, and the terms of any third-party services utilized.
- **Automated Messaging & Policy Compliance**: Automated or unauthorized messaging may violate platform terms. Users must ensure strict compliance with applicable privacy laws (e.g., GDPR, LGPD), user consent requirements, and platform guidelines.
- **No Warranty & Liability**: This software is provided "as is", without warranty of any kind, express or implied. The authors and contributors assume no liability for any account bans, data loss, service interruptions, or misuse of this library.

## License

This package is open-source software licensed under the [MIT License](LICENSE).
