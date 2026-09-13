# Changelog

All notable changes to `gowa-php` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Structured exception `GowaUnreachableException extends GowaRequestException` for network/transport failures, connection refused, DNS errors, and timeouts.
- Structured exception `MediaUnavailableException extends GowaRequestException` for permanent media download refusals (not found, does not contain media, unsupported media type).
- `statusCode`, `gowaCode`, and `gowaMessage` properties (and getters) on `GowaRequestException`.
- Optional `?callable $handler = null` parameter in `GowaClient::__construct()` allowing custom Guzzle handler injection (e.g. Laravel `Http::fake()` HandlerStack or Pest MockHandler) while retaining baseUrl, basic auth, timeout, and default headers.
- Support for ordered candidate phones (`string|list<string> $phones`) in `describeMedia()` to resolve outbound echo media stored under the device JID versus contact JID.
- Dedicated `?int $timeout = null` parameter in `downloadMedia()` for overriding client-level timeout during large media downloads.
- Automatic deletion of partial sink files and extraction of up to 2KB response bodies on HTTP 4xx or network failure in `downloadMedia()`.
- Pre-request validation for all `$deviceId` parameters rejecting empty or whitespace-only strings with `InvalidArgumentException`.

### Changed
- **Breaking**: `WebhookSignature::verify()` now strictly requires the `sha256=` signature prefix matching the GOWA multidevice server's `X-Hub-Signature-256: sha256=<hex>` format. Signatures without prefix return `false`.
- **Breaking**: `IncomingMessage::fromPayload()` no longer falls back to `from` when `chat_id` is absent; payloads lacking `chat_id` return `null`, preventing outbound echoes from opening conversations with the store itself.
- Guzzle HTTP client switched to `http_errors => false` so `results()` extracts exact server error codes and validation messages (e.g. `400 VALIDATION_ERROR ...`) or non-JSON 2KB snippets instead of truncated Guzzle exceptions.
- `avatar()` only returns `null` for legitimate absence of photos (404, non-SUCCESS code, empty results or missing URL). Connection failures throw `GowaUnreachableException` and 5xx server errors throw `GowaRequestException`.
- `device()` checks status code 404 directly instead of substring inspection.
- `updateWebhook()` now correctly sends `X-Device-Id` as an HTTP header instead of a query parameter.
- Fluent webhook event dispatcher `WebhookEvent` with `when()`, `onMessage()`, `onAck()`, `onReaction()`, and `otherwise()` (implementing `ArrayAccess` for 100% backwards compatibility with array indexing).
- Enum `MessageType` covering all WhatsApp message types with safe fallback to `MessageType::Unknown`.
- Fluent message routing on `IncomingMessage` with `when()`, `whenText()`, `whenLiveLocation()`, `whenLocation()`, `whenPoll()`, `whenEvent()`, `whenOrder()`, `whenContact()`, `whenContacts()`, `whenMedia()`, and `otherwise()`.
- Support for WhatsApp live location (`live_location`) incoming messages in `WebhookParser`.
- Support for WhatsApp poll messages (`poll`), calendar event requests (`event`), catalog orders (`order`), contacts (`contact`, `contacts_array`), instant video notes (`video_note`), and interactive messages (`interactive`, `list`) in `IncomingMessage`.
- DTOs: `LiveLocationPayload`, `PollPayload`, `EventPayload`, and `OrderPayload`.
- Factory methods `fromArray()` on `LocationPayload` and `ContactCard`.
- Helper methods on `IncomingMessage`: `isLocation()`, `isLiveLocation()`, `location()`, `liveLocation()`, `isPoll()`, `poll()`, `isEvent()`, `event()`, `isOrder()`, `order()`, `isContact()`, `contact()`, `contacts()`, `isInteractive()`, `isVideoNote()`, `isMedia()`.
- Added missing GOWA webhook events to `Event`: `LabelEdit`, `LabelAssociation`, `NewsletterJoined`, `NewsletterLeft`, `NewsletterMessage`, `NewsletterMute`.

## [1.0.0] - 2026-08-29

### Added
- Initial release of `gowa-php` SDK.
- Pure PHP HTTP client (`GowaClient`) wrapping the **go-whatsapp-web-multidevice** (GOWA) REST API.
- Support for device management: `createDevice()`, `device()`, `logout()`.
- Support for QR Code and 8-digit pairing code generation: `startQrPairing()`, `startCodePairing()`, `fetchQrImage()`.
- Support for text messages (`sendText`), link previews (`sendLink`), interactive polls (`sendPoll`), contact cards (`sendContacts`), location (`sendLocation`), emoji reactions (`sendReaction`), message forwarding (`forwardMessage`), text editing (`editMessage`), message revoking (`revokeMessage`), local deletion (`deleteMessage`), message starring (`starMessage`), audio played status (`markPlayed`), and read receipts with typing indicator (`markRead`).
- Support for media uploading via local files (`fromPath`), external URLs (`fromUrl`), and stream resources (`fromStream`) in `MediaUpload`.
- Audio voice note (PTT) support with automatic `audio/mp4` to `audio/m4a` MIME normalization.
- Security helpers: `GowaHost` anti-SSRF host validation and `WebhookSignature` HMAC SHA-256 header verification.
- Webhook event parser (`WebhookParser`) and event enum (`Event`) with incoming DTOs (`IncomingMessage`, `IncomingAck`, `IncomingReaction`).
- Complete Pest PHP test suite with 27 tests and 61 assertions.
- Documentation in English (`README.md`) and Portuguese (`README.pt.md`), along with `CONTRIBUTING.md` and `SECURITY.md`.

[Unreleased]: https://github.com/aguinaldotupy/gowa-php/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/aguinaldotupy/gowa-php/releases/tag/v1.0.0
