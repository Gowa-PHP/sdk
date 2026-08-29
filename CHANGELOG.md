# Changelog

All notable changes to `gowa-php` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
