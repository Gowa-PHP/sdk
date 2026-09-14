<?php

declare(strict_types=1);

namespace Gowa\Sdk;

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
use Gowa\Sdk\Exceptions\GowaUnreachableException;
use Gowa\Sdk\Exceptions\MediaUnavailableException;
use Gowa\Sdk\Exceptions\UnsupportedMediaException;
use Gowa\Sdk\Exceptions\UnsupportedOperationException;
use Gowa\Sdk\Security\GowaHost;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;

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
        ?callable $handler = null,
    ) {
        if ($client !== null) {
            $this->http = $client;
        } else {
            $options = [
                'base_uri'    => $this->config->getNormalizedBaseUrl() . '/',
                'auth'        => [$this->config->username, $this->config->password],
                'timeout'     => $this->config->timeout,
                'http_errors' => false,
                'headers'     => [
                    'Accept' => 'application/json',
                ],
            ];

            if ($handler !== null) {
                $options['handler'] = $handler instanceof HandlerStack ? $handler : HandlerStack::create($handler);
            }

            $this->http = new GuzzleClient($options);
        }
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
    public function createDevice(
        string $deviceId,
        string $webhookUrl,
        string $webhookSecret,
        array $events,
        bool $insecureSkipVerify = false,
    ): Device {
        self::assertValidDeviceId($deviceId);

        $response = $this->post('/devices', [
            'device_id'                    => $deviceId,
            'webhook_url'                  => $webhookUrl,
            'webhook_secret'               => $webhookSecret,
            'webhook_events'               => implode(',', $events),
            'webhook_insecure_skip_verify' => $insecureSkipVerify,
        ]);

        return Device::fromResults($this->results($response, 'create device'));
    }

    /**
     * Update the webhook URL, secret, and events for an existing device.
     *
     * @param list<string> $events
     * @return array<string, mixed>
     */
    public function updateWebhook(
        string $deviceId,
        string $webhookUrl,
        ?string $webhookSecret = null,
        array $events = [],
        bool $insecureSkipVerify = false,
    ): array {
        self::assertValidDeviceId($deviceId);

        $payload = [
            'webhook_url'                  => $webhookUrl,
            'webhook_insecure_skip_verify' => $insecureSkipVerify,
        ];

        if ($webhookSecret !== null) {
            $payload['webhook_secret'] = $webhookSecret;
        }

        if (! empty($events)) {
            $payload['webhook_events'] = implode(',', $events);
        }

        $response = $this->patch("/devices/{$deviceId}/webhook", $payload, [], [
            'X-Device-Id' => $deviceId,
        ]);

        return $this->results($response, 'update webhook');
    }

    /**
     * Start QR code pairing
     */
    public function startQrPairing(string $deviceId): Pairing
    {
        self::assertValidDeviceId($deviceId);

        $response = $this->get("/devices/{$deviceId}/login");

        return Pairing::fromQr($this->results($response, 'start qr pairing'));
    }

    /**
     * Start 8-digit code pairing
     */
    public function startCodePairing(string $deviceId, string $phone): Pairing
    {
        self::assertValidDeviceId($deviceId);

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
        self::assertValidDeviceId($deviceId);

        $response = $this->get("/devices/{$deviceId}");

        if ($response['status_code'] === 404) {
            return null;
        }

        return Device::fromResults($this->results($response, 'query device'));
    }

    /**
     * Disconnect device
     */
    public function logout(string $deviceId): void
    {
        self::assertValidDeviceId($deviceId);

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
            $res = $this->http->get($qrLink, [
                'http_errors'     => false,
                'allow_redirects' => false,
            ]);
        } catch (GuzzleException $e) {
            throw new GowaUnreachableException("Failed to download QR code image: {$e->getMessage()}", 0, $e);
        }

        $statusCode = $res->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            $rawBody = (string) $res->getBody();
            $snippet = substr($rawBody, 0, 2048);
            $errorMessage = $snippet !== ''
                ? "gowa refused fetch QR image: {$statusCode} {$snippet}"
                : "gowa refused fetch QR image: {$statusCode}";

            throw new GowaRequestException(
                message: $errorMessage,
                statusCode: $statusCode,
                gowaMessage: $snippet !== '' ? $snippet : null,
            );
        }

        return [
            'body'         => (string) $res->getBody(),
            'content_type' => $res->getHeaderLine('Content-Type') ?: 'image/png',
        ];
    }

    /**
     * Get contact profile picture
     */
    public function avatar(string $deviceId, string $phone): ?Avatar
    {
        self::assertValidDeviceId($deviceId);

        $response = $this->get('/user/avatar', [
            'phone'      => self::jid($phone),
            'is_preview' => 'true',
        ], [
            'X-Device-Id' => $deviceId,
        ]);

        $status = $response['status_code'];

        if ($status >= 500) {
            $this->results($response, 'get avatar');
        }

        if ($status === 404) {
            return null;
        }

        if ($status >= 400) {
            $this->results($response, 'get avatar');
        }

        $code = (string) ($response['body']['code'] ?? '');

        if ($code !== '' && $code !== 'SUCCESS') {
            return null;
        }

        $results = $response['body']['results'] ?? null;

        if (! is_array($results) || empty($results)) {
            return null;
        }

        return Avatar::fromResults($results);
    }

    /**
     * Send text message
     */
    public function sendText(string $deviceId, string $to, string $text, ?string $replyTo = null): SentMessage
    {
        self::assertValidDeviceId($deviceId);

        $body = [
            'phone'   => self::jid($to),
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
        self::assertValidDeviceId($deviceId);

        $upload = $media->upload;

        if ($upload === null) {
            throw new UnsupportedOperationException('Media upload requires a stream, local file, or valid URL.');
        }

        [$endpoint, $field] = $this->mediaEndpoint($media->type);
        $mime = $this->normalizeMime($upload->mimeType);

        $this->assertAcceptedMime($media->type, $mime);

        $multipart = [
            [
                'name'     => $field,
                'contents' => Utils::streamFor($upload->open()),
                'filename' => $upload->filename,
                'headers'  => ['Content-Type' => $mime],
            ],
            [
                'name'     => 'phone',
                'contents' => self::jid($to),
            ],
        ];

        if ($media->type === MediaType::Audio && $media->voice) {
            $multipart[] = [
                'name'     => 'ptt',
                'contents' => 'true',
            ];
        }

        if ($media->type !== MediaType::Audio && $media->caption !== null && $media->caption !== '') {
            $multipart[] = [
                'name'     => 'caption',
                'contents' => $media->caption,
            ];
        }

        if ($replyTo !== null && $replyTo !== '') {
            $multipart[] = [
                'name'     => 'reply_message_id',
                'contents' => $replyTo,
            ];
        }

        try {
            $res = $this->http->post($endpoint, [
                'headers'     => ['X-Device-Id' => $deviceId],
                'multipart'   => $multipart,
                'http_errors' => false,
            ]);

            $rawBody = (string) $res->getBody();
            $json = json_decode($rawBody, true);
            $parsed = [
                'status_code' => $res->getStatusCode(),
                'body'        => is_array($json) ? $json : [],
                'raw_body'    => $rawBody,
            ];

            return $this->sentResult($parsed, 'send media');
        } catch (GuzzleException $e) {
            throw new GowaUnreachableException("Network error sending media: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Send location payload
     */
    public function sendLocation(string $deviceId, string $to, LocationPayload $location, ?string $replyTo = null): SentMessage
    {
        self::assertValidDeviceId($deviceId);

        $body = [
            'phone'     => self::jid($to),
            'latitude'  => (string) $location->latitude,
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
        self::assertValidDeviceId($deviceId);

        if ($contacts === []) {
            throw new UnsupportedOperationException('Contact list is empty.');
        }

        $lastSent = null;

        foreach ($contacts as $contact) {
            $body = [
                'phone'         => self::jid($to),
                'contact_name'  => $contact->name,
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
        self::assertValidDeviceId($deviceId);

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
        self::assertValidDeviceId($deviceId);

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
        self::assertValidDeviceId($deviceId);

        $body = [
            'phone' => self::jid($to),
            'link'  => $link,
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
        self::assertValidDeviceId($deviceId);

        $body = [
            'phone'      => self::jid($to),
            'question'   => $question,
            'options'    => implode(',', $options),
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
        self::assertValidDeviceId($deviceId);

        $multipart = [
            [
                'name'     => 'sticker',
                'contents' => Utils::streamFor($upload->open()),
                'filename' => $upload->filename,
                'headers'  => ['Content-Type' => $upload->mimeType],
            ],
            [
                'name'     => 'phone',
                'contents' => self::jid($to),
            ],
        ];

        if ($replyTo !== null && $replyTo !== '') {
            $multipart[] = [
                'name'     => 'reply_message_id',
                'contents' => $replyTo,
            ];
        }

        try {
            $res = $this->http->post('send/sticker', [
                'headers'     => ['X-Device-Id' => $deviceId],
                'multipart'   => $multipart,
                'http_errors' => false,
            ]);

            $rawBody = (string) $res->getBody();
            $json = json_decode($rawBody, true);
            $parsed = [
                'status_code' => $res->getStatusCode(),
                'body'        => is_array($json) ? $json : [],
                'raw_body'    => $rawBody,
            ];

            return $this->sentResult($parsed, 'send sticker');
        } catch (GuzzleException $e) {
            throw new GowaUnreachableException("Network error sending sticker: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Edit text of a sent message
     */
    public function editMessage(string $deviceId, string $to, string $providerMessageId, string $newText): SentMessage
    {
        self::assertValidDeviceId($deviceId);

        $response = $this->post("/message/{$providerMessageId}/update", [
            'phone'   => self::jid($to),
            'message' => $newText,
        ], [], ['X-Device-Id' => $deviceId]);

        return $this->sentResult($response, 'edit message');
    }

    /**
     * Revoke message for everyone
     */
    public function revokeMessage(string $deviceId, string $to, string $providerMessageId): void
    {
        self::assertValidDeviceId($deviceId);

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
        self::assertValidDeviceId($deviceId);

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
        self::assertValidDeviceId($deviceId);

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
        self::assertValidDeviceId($deviceId);

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
        self::assertValidDeviceId($deviceId);

        if ($withTyping) {
            $presenceResponse = $this->post('/send/chat-presence', [
                'phone'  => self::jid($to),
                'action' => 'start',
            ], [], ['X-Device-Id' => $deviceId]);

            $this->results($presenceResponse, 'start chat presence');
        }

        $response = $this->post("/message/{$providerMessageId}/read", [
            'phone' => self::jid($to),
        ], [], ['X-Device-Id' => $deviceId]);

        $this->results($response, 'mark read');
    }

    /**
     * Describe and prepare inbound media for download.
     * Accepts a single phone or an ordered list of candidate phones (e.g. for echo messages).
     *
     * @param string|list<string> $phones
     */
    public function describeMedia(string $deviceId, string|array $phones, string $providerMessageId): ?RemoteMedia
    {
        self::assertValidDeviceId($deviceId);

        $phoneList = array_values(array_filter(
            array_map(
                static fn(mixed $phone): string => is_string($phone) ? trim($phone) : '',
                is_array($phones) ? $phones : [$phones],
            ),
            static fn(string $phone): bool => $phone !== '',
        ));

        if (empty($phoneList)) {
            throw new InvalidArgumentException('At least one phone candidate must be provided.');
        }

        $lastResponse = null;
        $results = null;

        foreach ($phoneList as $phone) {
            $response = $this->get("/message/{$providerMessageId}/download", [
                'phone' => self::jid($phone),
            ], [
                'X-Device-Id' => $deviceId,
            ]);

            $lastResponse = $response;
            $statusCode = $response['status_code'];

            if ($statusCode === 404) {
                return null;
            }

            $body = $response['body'];
            $errorMessage = is_array($body) ? (string) ($body['message'] ?? '') : '';

            if (str_contains($errorMessage, 'does not belong to chat')) {
                continue;
            }

            if ($this->isPermanentMediaFailure($errorMessage)) {
                $code = is_array($body) ? (string) ($body['code'] ?? '') : '';

                throw new MediaUnavailableException(
                    message: "gowa refused to prepare media: {$statusCode} {$code} {$errorMessage}",
                    statusCode: $statusCode,
                    gowaCode: $code !== '' ? $code : null,
                    gowaMessage: $errorMessage !== '' ? $errorMessage : null,
                );
            }

            $results = $this->results($response, 'prepare media');
            break;
        }

        /** @var array<string, mixed> $results */
        $results ??= $this->results($lastResponse ?? [], 'prepare media');

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
     * Download decrypted media bytes.
     */
    public function downloadMedia(string $mediaUrl, string $destinationPath, ?int $timeout = null): void
    {
        GowaHost::assertBelongsToServer($mediaUrl, $this->config->baseUrl);

        $options = [
            'sink'            => $destinationPath,
            'http_errors'     => false,
            'allow_redirects' => false,
        ];

        if ($timeout !== null) {
            $options['timeout'] = $timeout;
        }

        try {
            $response = $this->http->get($mediaUrl, $options);
        } catch (GuzzleException $e) {
            if (is_file($destinationPath)) {
                @unlink($destinationPath);
            }

            throw new GowaUnreachableException("Failed to download media bytes: {$e->getMessage()}", 0, $e);
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            $body = is_file($destinationPath)
                ? (string) file_get_contents($destinationPath, false, null, 0, 2048)
                : '';

            if (is_file($destinationPath)) {
                @unlink($destinationPath);
            }

            $suffix = $body !== '' ? " — {$body}" : '';

            throw new GowaRequestException(
                message: "gowa refused to deliver media: {$statusCode}{$suffix}",
                statusCode: $statusCode,
                gowaMessage: $body !== '' ? $body : null,
            );
        }
    }

    private static function assertValidDeviceId(string $deviceId): void
    {
        if (trim($deviceId) === '') {
            throw new InvalidArgumentException('Device ID cannot be empty or whitespace.');
        }
    }

    private function isPermanentMediaFailure(string $message): bool
    {
        return str_contains($message, 'does not contain downloadable media')
            || str_contains($message, 'not found')
            || str_contains($message, 'unsupported media type');
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $queryParams
     * @param array<string, string> $headers
     * @return array{status_code: int, body: array<string, mixed>, raw_body: string}
     */
    private function patch(string $endpoint, array $body = [], array $queryParams = [], array $headers = []): array
    {
        try {
            $res = $this->http->patch(ltrim($endpoint, '/'), [
                'query'       => $queryParams,
                'json'        => $body,
                'headers'     => $headers,
                'http_errors' => false,
            ]);

            $rawBody = (string) $res->getBody();
            $json = json_decode($rawBody, true);

            return [
                'status_code' => $res->getStatusCode(),
                'body'        => is_array($json) ? $json : [],
                'raw_body'    => $rawBody,
            ];
        } catch (GuzzleException $e) {
            throw new GowaUnreachableException("HTTP PATCH {$endpoint} error: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $queryParams
     * @param array<string, string> $headers
     * @return array{status_code: int, body: array<string, mixed>, raw_body: string}
     */
    private function post(string $endpoint, array $body = [], array $queryParams = [], array $headers = []): array
    {
        try {
            $res = $this->http->post(ltrim($endpoint, '/'), [
                'query'       => $queryParams,
                'json'        => $body,
                'headers'     => $headers,
                'http_errors' => false,
            ]);

            $rawBody = (string) $res->getBody();
            $json = json_decode($rawBody, true);

            return [
                'status_code' => $res->getStatusCode(),
                'body'        => is_array($json) ? $json : [],
                'raw_body'    => $rawBody,
            ];
        } catch (GuzzleException $e) {
            throw new GowaUnreachableException("HTTP POST {$endpoint} error: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * @param array<string, mixed> $queryParams
     * @param array<string, string> $headers
     * @return array{status_code: int, body: array<string, mixed>, raw_body: string}
     */
    private function get(string $endpoint, array $queryParams = [], array $headers = []): array
    {
        try {
            $res = $this->http->get(ltrim($endpoint, '/'), [
                'query'       => $queryParams,
                'headers'     => $headers,
                'http_errors' => false,
            ]);

            $rawBody = (string) $res->getBody();
            $json = json_decode($rawBody, true);

            return [
                'status_code' => $res->getStatusCode(),
                'body'        => is_array($json) ? $json : [],
                'raw_body'    => $rawBody,
            ];
        } catch (GuzzleException $e) {
            throw new GowaUnreachableException("HTTP GET {$endpoint} error: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * @param array{status_code: int, body: array<string, mixed>, raw_body?: string} $response
     * @return array<string, mixed>
     */
    private function results(array $response, string $action): array
    {
        $status = (int) ($response['status_code'] ?? 200);
        $body = $response['body'] ?? [];
        $rawBody = (string) ($response['raw_body'] ?? '');
        $isJson = is_array($body) && ! empty($body);
        $code = is_array($body) ? (string) ($body['code'] ?? '') : '';
        $gowaMsg = is_array($body) ? (string) ($body['message'] ?? '') : '';

        if ($status >= 400 || ($code !== '' && $code !== 'SUCCESS')) {
            if ($isJson && ($code !== '' || $gowaMsg !== '')) {
                $details = trim("{$code} {$gowaMsg}");
                $errorMessage = "gowa refused {$action}: {$status} {$details}";

                throw new GowaRequestException(
                    message: $errorMessage,
                    statusCode: $status,
                    gowaCode: $code !== '' ? $code : null,
                    gowaMessage: $gowaMsg !== '' ? $gowaMsg : null,
                );
            }

            $snippet = substr($rawBody, 0, 2048);
            $errorMessage = $snippet !== ''
                ? "gowa refused {$action}: {$status} {$snippet}"
                : "gowa refused {$action}: {$status}";

            throw new GowaRequestException(
                message: $errorMessage,
                statusCode: $status,
                gowaCode: null,
                gowaMessage: $snippet !== '' ? $snippet : null,
            );
        }

        $results = $response['body']['results'] ?? null;

        return is_array($results) ? $results : [];
    }

    /**
     * @param array{status_code: int, body: array<string, mixed>, raw_body?: string} $response
     */
    private function sentResult(array $response, string $action): SentMessage
    {
        $results = $this->results($response, $action);
        $id = (string) ($results['message_id'] ?? $response['body']['results']['message_id'] ?? '');

        if ($id === '') {
            throw new GowaRequestException(
                message: "gowa accepted {$action} without returning a message_id.",
                statusCode: $response['status_code'] ?? null,
            );
        }

        return new SentMessage(providerMessageId: $id, raw: $response['body']);
    }

    private function normalizeMime(string $mimeType): string
    {
        $mime = strtolower(trim(explode(';', $mimeType)[0]));

        return match ($mime) {
            'audio/mp4' => 'audio/m4a',
            default     => $mime,
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
            MediaType::Image    => ['send/image', 'image'],
            MediaType::Video    => ['send/video', 'video'],
            MediaType::Audio    => ['send/audio', 'audio'],
            MediaType::Document => ['send/file', 'file'],
        };
    }
}
