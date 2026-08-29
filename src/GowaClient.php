<?php

declare(strict_types=1);

namespace Gowa\Sdk;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Utils;
use Gowa\Sdk\Dto\Avatar;
use Gowa\Sdk\Dto\ContactCard;
use Gowa\Sdk\Dto\Device;
use Gowa\Sdk\Dto\LocationPayload;
use Gowa\Sdk\Dto\MediaPayload;
use Gowa\Sdk\Dto\MediaType;
use Gowa\Sdk\Dto\MediaUpload;
use Gowa\Sdk\Dto\Pairing;
use Gowa\Sdk\Dto\RemoteMedia;
use Gowa\Sdk\Dto\SentMessage;
use Gowa\Sdk\Exceptions\GowaRequestException;
use Gowa\Sdk\Exceptions\UnsupportedMediaException;
use Gowa\Sdk\Exceptions\UnsupportedOperationException;
use Gowa\Sdk\Security\GowaHost;

class GowaClient
{
    private readonly GuzzleClient $http;

    /**
     * Table of accepted MIME types per media category in GOWA
     *
     * @var array<string, list<string>>
     */
    private const ACCEPTED_MIMES = [
        'image' => ['image/jpeg', 'image/jpg', 'image/png'],
        'video' => ['video/mp4', 'video/x-matroska', 'video/avi', 'video/x-msvideo'],
        'audio' => [
            'audio/aac', 'audio/amr', 'audio/flac', 'audio/m4a', 'audio/m4r',
            'audio/mp3', 'audio/mpeg', 'audio/ogg', 'audio/wma', 'audio/x-ms-wma',
            'audio/wav', 'audio/vnd.wav', 'audio/vnd.wave', 'audio/wave',
            'audio/x-pn-wav', 'audio/x-wav',
        ],
    ];

    public function __construct(
        public readonly Config $config,
        ?GuzzleClient $client = null,
    ) {
        $this->http = $client ?? new GuzzleClient([
            'base_uri' => $this->config->getNormalizedBaseUrl() . '/',
            'auth' => [$this->config->username, $this->config->password],
            'timeout' => $this->config->timeout,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }

    public static function jid(string $to): string
    {
        return str_contains($to, '@') ? $to : $to . '@s.whatsapp.net';
    }

    public function isConfigured(): bool
    {
        return $this->config->isConfigured();
    }

    /**
     * Register a device and its webhook URL
     *
     * @param list<string> $events
     */
    public function createDevice(string $deviceId, string $webhookUrl, string $webhookSecret, array $events): Device
    {
        $response = $this->post('/devices', [
            'device_id' => $deviceId,
            'webhook_url' => $webhookUrl,
            'webhook_secret' => $webhookSecret,
            'webhook_events' => implode(',', $events),
        ]);

        return Device::fromResults($this->results($response, 'create device'));
    }

    /**
     * Start QR code pairing
     */
    public function startQrPairing(string $deviceId): Pairing
    {
        $response = $this->get("/devices/{$deviceId}/login");

        return Pairing::fromQr($this->results($response, 'start qr pairing'));
    }

    /**
     * Start 8-digit code pairing
     */
    public function startCodePairing(string $deviceId, string $phone): Pairing
    {
        $response = $this->post("/devices/{$deviceId}/login/code", [], [
            'phone' => $phone,
        ]);

        return Pairing::fromCode($this->results($response, 'request pairing code'));
    }

    /**
     * Query device state in GOWA
     */
    public function device(string $deviceId): ?Device
    {
        try {
            $response = $this->get("/devices/{$deviceId}");
        } catch (GowaRequestException $e) {
            if (str_contains($e->getMessage(), '404')) {
                return null;
            }
            throw $e;
        }

        if (($response['status_code'] ?? 200) === 404) {
            return null;
        }

        return Device::fromResults($this->results($response, 'query device'));
    }

    /**
     * Disconnect device
     */
    public function logout(string $deviceId): void
    {
        $response = $this->post("/devices/{$deviceId}/logout");
        $this->results($response, 'logout device');
    }

    /**
     * Fetch QR code image via secure proxy
     *
     * @return array{body: string, content_type: string}
     */
    public function fetchQrImage(string $qrLink): array
    {
        GowaHost::assertBelongsToServer($qrLink, $this->config->baseUrl);

        try {
            $res = $this->http->get($qrLink);
            return [
                'body' => (string) $res->getBody(),
                'content_type' => $res->getHeaderLine('Content-Type') ?: 'image/png',
            ];
        } catch (GuzzleException $e) {
            throw new GowaRequestException("Failed to download QR code image: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get contact profile picture
     */
    public function avatar(string $deviceId, string $phone): ?Avatar
    {
        try {
            $response = $this->get('/user/avatar', [
                'phone' => self::jid($phone),
                'is_preview' => 'true',
            ], [
                'X-Device-Id' => $deviceId,
            ]);
        } catch (GowaRequestException $e) {
            return null;
        }

        $code = (string) ($response['body']['code'] ?? '');

        if (($response['status_code'] ?? 200) !== 200 || ($code !== '' && $code !== 'SUCCESS')) {
            return null;
        }

        $results = $response['body']['results'] ?? null;

        return is_array($results) ? Avatar::fromResults($results) : null;
    }

    /**
     * Send text message
     */
    public function sendText(string $deviceId, string $to, string $text, ?string $replyTo = null): SentMessage
    {
        $body = [
            'phone' => self::jid($to),
            'message' => $text,
        ];

        if ($replyTo !== null && $replyTo !== '') {
            $body['reply_message_id'] = $replyTo;
        }

        $response = $this->post('/send/message', $body, [], ['X-Device-Id' => $deviceId]);

        return $this->sentResult($response, 'send text message');
    }

    /**
     * Send media file (image, video, audio, document)
     */
    public function sendMedia(string $deviceId, string $to, MediaPayload $media, ?string $replyTo = null): SentMessage
    {
        $upload = $media->upload;

        if ($upload === null) {
            throw new UnsupportedOperationException('Media upload requires a stream, local file, or valid URL.');
        }

        [$endpoint, $field] = $this->mediaEndpoint($media->type);
        $mime = $this->normalizeMime($upload->mimeType);

        $this->assertAcceptedMime($media->type, $mime);

        $multipart = [
            [
                'name' => $field,
                'contents' => Utils::streamFor($upload->open()),
                'filename' => $upload->filename,
                'headers' => ['Content-Type' => $mime],
            ],
            [
                'name' => 'phone',
                'contents' => self::jid($to),
            ],
        ];

        if ($media->type === MediaType::Audio && $media->voice) {
            $multipart[] = [
                'name' => 'ptt',
                'contents' => 'true',
            ];
        }

        if ($media->type !== MediaType::Audio && $media->caption !== null && $media->caption !== '') {
            $multipart[] = [
                'name' => 'caption',
                'contents' => $media->caption,
            ];
        }

        if ($replyTo !== null && $replyTo !== '') {
            $multipart[] = [
                'name' => 'reply_message_id',
                'contents' => $replyTo,
            ];
        }

        try {
            $res = $this->http->post($endpoint, [
                'headers' => ['X-Device-Id' => $deviceId],
                'multipart' => $multipart,
            ]);

            $json = json_decode((string) $res->getBody(), true);
            $parsed = [
                'status_code' => $res->getStatusCode(),
                'body' => is_array($json) ? $json : [],
            ];

            return $this->sentResult($parsed, 'send media');
        } catch (GuzzleException $e) {
            throw new GowaRequestException("Network error sending media: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Send location payload
     */
    public function sendLocation(string $deviceId, string $to, LocationPayload $location, ?string $replyTo = null): SentMessage
    {
        $body = [
            'phone' => self::jid($to),
            'latitude' => (string) $location->latitude,
            'longitude' => (string) $location->longitude,
        ];

        if ($replyTo !== null && $replyTo !== '') {
            $body['reply_message_id'] = $replyTo;
        }

        $response = $this->post('/send/location', $body, [], ['X-Device-Id' => $deviceId]);

        return $this->sentResult($response, 'send location');
    }

    /**
     * Send contact cards
     *
     * @param list<ContactCard> $contacts
     */
    public function sendContacts(string $deviceId, string $to, array $contacts, ?string $replyTo = null): SentMessage
    {
        if ($contacts === []) {
            throw new UnsupportedOperationException('Contact list is empty.');
        }

        $lastSent = null;

        foreach ($contacts as $contact) {
            $body = [
                'phone' => self::jid($to),
                'contact_name' => $contact->name,
                'contact_phone' => (string) ($contact->phones[0]['phone'] ?? ''),
            ];

            if ($replyTo !== null && $replyTo !== '') {
                $body['reply_message_id'] = $replyTo;
            }

            $response = $this->post('/send/contact', $body, [], ['X-Device-Id' => $deviceId]);
            $lastSent = $this->sentResult($response, 'send contact');
        }

        /** @var SentMessage */
        return $lastSent;
    }

    /**
     * Send emoji reaction
     */
    public function sendReaction(string $deviceId, string $to, string $providerMessageId, string $emoji): SentMessage
    {
        $response = $this->post("/message/{$providerMessageId}/reaction", [
            'phone' => self::jid($to),
            'emoji' => $emoji,
        ], [], ['X-Device-Id' => $deviceId]);

        return $this->sentResult($response, 'send reaction');
    }

    /**
     * Forward an existing message to another chat
     */
    public function forwardMessage(string $deviceId, string $to, string $providerMessageId): SentMessage
    {
        $response = $this->post("/message/{$providerMessageId}/forward", [
            'phone' => self::jid($to),
        ], [], ['X-Device-Id' => $deviceId]);

        return $this->sentResult($response, 'forward message');
    }

    /**
     * Send URL link with preview
     */
    public function sendLink(string $deviceId, string $to, string $link, ?string $caption = null, ?string $replyTo = null): SentMessage
    {
        $body = [
            'phone' => self::jid($to),
            'link' => $link,
        ];

        if ($caption !== null && $caption !== '') {
            $body['caption'] = $caption;
        }

        if ($replyTo !== null && $replyTo !== '') {
            $body['reply_message_id'] = $replyTo;
        }

        $response = $this->post('/send/link', $body, [], ['X-Device-Id' => $deviceId]);

        return $this->sentResult($response, 'send link');
    }

    /**
     * Send an interactive poll
     *
     * @param list<string> $options
     */
    public function sendPoll(string $deviceId, string $to, string $question, array $options, int $maxSelections = 1, ?string $replyTo = null): SentMessage
    {
        $body = [
            'phone' => self::jid($to),
            'question' => $question,
            'options' => implode(',', $options),
            'max_answer' => $maxSelections,
        ];

        if ($replyTo !== null && $replyTo !== '') {
            $body['reply_message_id'] = $replyTo;
        }

        $response = $this->post('/send/poll', $body, [], ['X-Device-Id' => $deviceId]);

        return $this->sentResult($response, 'send poll');
    }

    /**
     * Send WebP sticker
     */
    public function sendSticker(string $deviceId, string $to, MediaUpload $upload, ?string $replyTo = null): SentMessage
    {
        $multipart = [
            [
                'name' => 'sticker',
                'contents' => Utils::streamFor($upload->open()),
                'filename' => $upload->filename,
                'headers' => ['Content-Type' => $upload->mimeType],
            ],
            [
                'name' => 'phone',
                'contents' => self::jid($to),
            ],
        ];

        if ($replyTo !== null && $replyTo !== '') {
            $multipart[] = [
                'name' => 'reply_message_id',
                'contents' => $replyTo,
            ];
        }

        try {
            $res = $this->http->post('send/sticker', [
                'headers' => ['X-Device-Id' => $deviceId],
                'multipart' => $multipart,
            ]);

            $json = json_decode((string) $res->getBody(), true);
            $parsed = [
                'status_code' => $res->getStatusCode(),
                'body' => is_array($json) ? $json : [],
            ];

            return $this->sentResult($parsed, 'send sticker');
        } catch (GuzzleException $e) {
            throw new GowaRequestException("Network error sending sticker: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Edit text of a sent message
     */
    public function editMessage(string $deviceId, string $to, string $providerMessageId, string $newText): SentMessage
    {
        $response = $this->post("/message/{$providerMessageId}/update", [
            'phone' => self::jid($to),
            'message' => $newText,
        ], [], ['X-Device-Id' => $deviceId]);

        return $this->sentResult($response, 'edit message');
    }

    /**
     * Revoke message for everyone
     */
    public function revokeMessage(string $deviceId, string $to, string $providerMessageId): void
    {
        $response = $this->post("/message/{$providerMessageId}/revoke", [
            'phone' => self::jid($to),
        ], [], ['X-Device-Id' => $deviceId]);

        $this->results($response, 'revoke message');
    }

    /**
     * Delete message locally
     */
    public function deleteMessage(string $deviceId, string $to, string $providerMessageId): void
    {
        $response = $this->post("/message/{$providerMessageId}/delete", [
            'phone' => self::jid($to),
        ], [], ['X-Device-Id' => $deviceId]);

        $this->results($response, 'delete message');
    }

    /**
     * Star or unstar a message
     */
    public function starMessage(string $deviceId, string $to, string $providerMessageId, bool $star = true): void
    {
        $endpoint = $star ? "/message/{$providerMessageId}/star" : "/message/{$providerMessageId}/unstar";

        $response = $this->post($endpoint, [
            'phone' => self::jid($to),
        ], [], ['X-Device-Id' => $deviceId]);

        $this->results($response, ($star ? 'star' : 'unstar') . ' message');
    }

    /**
     * Mark audio message as played
     */
    public function markPlayed(string $deviceId, string $to, string $providerMessageId): void
    {
        $response = $this->post("/message/{$providerMessageId}/played", [
            'phone' => self::jid($to),
        ], [], ['X-Device-Id' => $deviceId]);

        $this->results($response, 'mark audio as played');
    }

    /**
     * Mark message as read
     */
    public function markRead(string $deviceId, string $to, string $providerMessageId, bool $withTyping = false): void
    {
        if ($withTyping) {
            $this->post('/send/chat-presence', [
                'phone' => self::jid($to),
                'action' => 'start',
            ], [], ['X-Device-Id' => $deviceId]);
        }

        $response = $this->post("/message/{$providerMessageId}/read", [
            'phone' => self::jid($to),
        ], [], ['X-Device-Id' => $deviceId]);

        $this->results($response, 'mark read');
    }

    /**
     * Describe and prepare inbound media for download
     */
    public function describeMedia(string $deviceId, string $to, string $providerMessageId): ?RemoteMedia
    {
        try {
            $response = $this->get("/message/{$providerMessageId}/download", [
                'phone' => self::jid($to),
            ], ['X-Device-Id' => $deviceId]);
        } catch (GowaRequestException $e) {
            if (str_contains($e->getMessage(), '404')) {
                return null;
            }
            throw $e;
        }

        if (($response['status_code'] ?? 200) === 404) {
            return null;
        }

        $results = $this->results($response, 'prepare media');

        $url = (string) ($results['file_path'] ?? $results['file_url'] ?? '');

        if ($url === '') {
            return null;
        }

        $filename = $results['filename'] ?? null;

        return new RemoteMedia(
            url: $url,
            mimeType: null,
            sizeBytes: (int) ($results['file_size'] ?? 0),
            filename: is_string($filename) && $filename !== '' ? $filename : null,
        );
    }

    /**
     * Download decrypted media bytes
     */
    public function downloadMedia(string $mediaUrl, string $destinationPath): void
    {
        GowaHost::assertBelongsToServer($mediaUrl, $this->config->baseUrl);

        try {
            $this->http->get($mediaUrl, ['sink' => $destinationPath]);
        } catch (GuzzleException $e) {
            throw new GowaRequestException("Failed to download media bytes: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $queryParams
     * @param array<string, string> $headers
     * @return array{status_code: int, body: array<string, mixed>}
     */
    private function post(string $endpoint, array $body = [], array $queryParams = [], array $headers = []): array
    {
        try {
            $res = $this->http->post(ltrim($endpoint, '/'), [
                'query' => $queryParams,
                'json' => $body,
                'headers' => $headers,
            ]);

            $json = json_decode((string) $res->getBody(), true);

            return [
                'status_code' => $res->getStatusCode(),
                'body' => is_array($json) ? $json : [],
            ];
        } catch (GuzzleException $e) {
            throw new GowaRequestException("HTTP POST {$endpoint} error: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * @param array<string, mixed> $queryParams
     * @param array<string, string> $headers
     * @return array{status_code: int, body: array<string, mixed>}
     */
    private function get(string $endpoint, array $queryParams = [], array $headers = []): array
    {
        try {
            $res = $this->http->get(ltrim($endpoint, '/'), [
                'query' => $queryParams,
                'headers' => $headers,
            ]);

            $json = json_decode((string) $res->getBody(), true);

            return [
                'status_code' => $res->getStatusCode(),
                'body' => is_array($json) ? $json : [],
            ];
        } catch (GuzzleException $e) {
            throw new GowaRequestException("HTTP GET {$endpoint} error: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * @param array{status_code: int, body: array<string, mixed>} $response
     * @return array<string, mixed>
     */
    private function results(array $response, string $action): array
    {
        $code = (string) ($response['body']['code'] ?? '');
        $status = $response['status_code'];

        if ($status >= 400 || ($code !== '' && $code !== 'SUCCESS')) {
            $message = (string) ($response['body']['message'] ?? '');
            throw new GowaRequestException("gowa refused {$action}: {$status} {$code} {$message}");
        }

        $results = $response['body']['results'] ?? null;

        return is_array($results) ? $results : [];
    }

    /**
     * @param array{status_code: int, body: array<string, mixed>} $response
     */
    private function sentResult(array $response, string $action): SentMessage
    {
        $results = $this->results($response, $action);
        $id = (string) ($results['message_id'] ?? $response['body']['results']['message_id'] ?? '');

        if ($id === '') {
            throw new GowaRequestException("gowa accepted {$action} without returning a message_id.");
        }

        return new SentMessage(providerMessageId: $id, raw: $response['body']);
    }

    private function normalizeMime(string $mimeType): string
    {
        $mime = strtolower(trim(explode(';', $mimeType)[0]));

        return match ($mime) {
            'audio/mp4' => 'audio/m4a',
            default => $mime,
        };
    }

    private function assertAcceptedMime(MediaType $type, string $mime): void
    {
        $aceitos = self::ACCEPTED_MIMES[$type->value] ?? null;

        if ($aceitos === null || in_array($mime, $aceitos, true)) {
            return;
        }

        throw new UnsupportedMediaException(
            "GOWA does not support media type {$type->value} in format ({$mime}).",
        );
    }

    /**
     * @return array{string, string}
     */
    private function mediaEndpoint(MediaType $type): array
    {
        return match ($type) {
            MediaType::Image => ['send/image', 'image'],
            MediaType::Video => ['send/video', 'video'],
            MediaType::Audio => ['send/audio', 'audio'],
            MediaType::Document => ['send/file', 'file'],
        };
    }
}
