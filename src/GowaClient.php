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
use Gowa\Sdk\Dto\Pairing;
use Gowa\Sdk\Dto\RemoteMedia;
use Gowa\Sdk\Dto\SentMessage;
use Gowa\Sdk\Exceptions\GowaRequestException;
use Gowa\Sdk\Exceptions\UnsupportedMediaException;
use Gowa\Sdk\Exceptions\UnsupportedOperationException;
use Gowa\Sdk\Security\GowaHost;
use Psr\Http\Client\ClientInterface;

class GowaClient
{
    private readonly GuzzleClient $http;

    /**
     * Tabela de mimes aceitos pelo GOWA por tipo
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
     * Registra um dispositivo e seu webhook
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

        return Device::fromResults($this->results($response, 'criar o aparelho'));
    }

    /**
     * Inicia o pareamento por QR Code
     */
    public function startQrPairing(string $deviceId): Pairing
    {
        $response = $this->get("/devices/{$deviceId}/login");

        return Pairing::fromQr($this->results($response, 'começar o pareamento'));
    }

    /**
     * Inicia o pareamento por Código de 8 dígitos
     */
    public function startCodePairing(string $deviceId, string $phone): Pairing
    {
        $response = $this->post("/devices/{$deviceId}/login/code", [], [
            'phone' => $phone,
        ]);

        return Pairing::fromCode($this->results($response, 'pedir o código de pareamento'));
    }

    /**
     * Consulta o estado do dispositivo no GOWA
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

        return Device::fromResults($this->results($response, 'consultar o aparelho'));
    }

    /**
     * Desconecta o dispositivo
     */
    public function logout(string $deviceId): void
    {
        $response = $this->post("/devices/{$deviceId}/logout");
        $this->results($response, 'desconectar o aparelho');
    }

    /**
     * Baixa a imagem do QR Code via proxy seguro
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
            throw new GowaRequestException("Falha ao baixar imagem do QR Code: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Obtém a foto de perfil de um contato
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
     * Envia mensagem de texto
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

        return $this->sentResult($response, 'enviar a mensagem');
    }

    /**
     * Envia arquivo de mídia (imagem, vídeo, áudio, documento)
     */
    public function sendMedia(string $deviceId, string $to, MediaPayload $media, ?string $replyTo = null): SentMessage
    {
        $upload = $media->upload;

        if ($upload === null) {
            throw new UnsupportedOperationException('Envio de mídia exige um arquivo local/stream.');
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

            return $this->sentResult($parsed, 'enviar a mídia');
        } catch (GuzzleException $e) {
            throw new GowaRequestException("Falha de rede ao enviar mídia: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Envia localização
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

        return $this->sentResult($response, 'enviar a localização');
    }

    /**
     * Envia cartões de contato
     *
     * @param list<ContactCard> $contacts
     */
    public function sendContacts(string $deviceId, string $to, array $contacts, ?string $replyTo = null): SentMessage
    {
        if ($contacts === []) {
            throw new UnsupportedOperationException('Lista de contatos vazia.');
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
            $lastSent = $this->sentResult($response, 'enviar o contato');
        }

        /** @var SentMessage */
        return $lastSent;
    }

    /**
     * Envia reação com emoji
     */
    public function sendReaction(string $deviceId, string $to, string $providerMessageId, string $emoji): SentMessage
    {
        $response = $this->post("/message/{$providerMessageId}/reaction", [
            'phone' => self::jid($to),
            'emoji' => $emoji,
        ], [], ['X-Device-Id' => $deviceId]);

        return $this->sentResult($response, 'enviar a reação');
    }

    /**
     * Encaminha uma mensagem existente para outro destinatário
     */
    public function forwardMessage(string $deviceId, string $to, string $providerMessageId): SentMessage
    {
        $response = $this->post("/message/{$providerMessageId}/forward", [
            'phone' => self::jid($to),
        ], [], ['X-Device-Id' => $deviceId]);

        return $this->sentResult($response, 'encaminhar a mensagem');
    }

    /**
     * Envia um link com prévia visual
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

        return $this->sentResult($response, 'enviar o link');
    }

    /**
     * Envia uma enquete (Poll)
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

        return $this->sentResult($response, 'enviar a enquete');
    }

    /**
     * Envia uma figurinha (Sticker WebP)
     */
    public function sendSticker(string $deviceId, string $to, Dto\MediaUpload $upload, ?string $replyTo = null): SentMessage
    {
        $multipart = [
            [
                'name' => 'sticker',
                'contents' => GuzzleHttp\Psr7\Utils::streamFor($upload->open()),
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

            return $this->sentResult($parsed, 'enviar a figurinha');
        } catch (GuzzleHttp\Exception\GuzzleException $e) {
            throw new GowaRequestException("Falha de rede ao enviar figurinha: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Edita o texto de uma mensagem enviada
     */
    public function editMessage(string $deviceId, string $to, string $providerMessageId, string $newText): SentMessage
    {
        $response = $this->post("/message/{$providerMessageId}/update", [
            'phone' => self::jid($to),
            'message' => $newText,
        ], [], ['X-Device-Id' => $deviceId]);

        return $this->sentResult($response, 'editar a mensagem');
    }

    /**
     * Apaga a mensagem para todos (Revoke)
     */
    public function revokeMessage(string $deviceId, string $to, string $providerMessageId): void
    {
        $response = $this->post("/message/{$providerMessageId}/revoke", [
            'phone' => self::jid($to),
        ], [], ['X-Device-Id' => $deviceId]);

        $this->results($response, 'revogar a mensagem');
    }

    /**
     * Apaga a mensagem localmente (Delete)
     */
    public function deleteMessage(string $deviceId, string $to, string $providerMessageId): void
    {
        $response = $this->post("/message/{$providerMessageId}/delete", [
            'phone' => self::jid($to),
        ], [], ['X-Device-Id' => $deviceId]);

        $this->results($response, 'deletar a mensagem');
    }

    /**
     * Favorita ou desfavorita uma mensagem (Star / Unstar)
     */
    public function starMessage(string $deviceId, string $to, string $providerMessageId, bool $star = true): void
    {
        $endpoint = $star ? "/message/{$providerMessageId}/star" : "/message/{$providerMessageId}/unstar";

        $response = $this->post($endpoint, [
            'phone' => self::jid($to),
        ], [], ['X-Device-Id' => $deviceId]);

        $this->results($response, ($star ? 'favoritar' : 'desfavoritar') . ' a mensagem');
    }

    /**
     * Marca um áudio como reproduzido (Mark Played)
     */
    public function markPlayed(string $deviceId, string $to, string $providerMessageId): void
    {
        $response = $this->post("/message/{$providerMessageId}/played", [
            'phone' => self::jid($to),
        ], [], ['X-Device-Id' => $deviceId]);

        $this->results($response, 'marcar áudio como reproduzido');
    }

    /**
     * Confirma leitura de uma mensagem
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

        $this->results($response, 'confirmar a leitura');
    }

    /**
     * Prepara e descreve a mídia de uma mensagem recebida para download
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

        $results = $this->results($response, 'preparar a mídia');

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
     * Baixa os bytes da mídia já decifrada pelo GOWA
     */
    public function downloadMedia(string $mediaUrl, string $destinationPath): void
    {
        GowaHost::assertBelongsToServer($mediaUrl, $this->config->baseUrl);

        try {
            $this->http->get($mediaUrl, ['sink' => $destinationPath]);
        } catch (GuzzleException $e) {
            throw new GowaRequestException("Falha ao baixar os bytes da mídia: {$e->getMessage()}", 0, $e);
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
            throw new GowaRequestException("Erro HTTP POST {$endpoint}: {$e->getMessage()}", 0, $e);
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
            throw new GowaRequestException("Erro HTTP GET {$endpoint}: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * @param array{status_code: int, body: array<string, mixed>} $response
     * @return array<string, mixed>
     */
    private function results(array $response, string $acao): array
    {
        $code = (string) ($response['body']['code'] ?? '');
        $status = $response['status_code'];

        if ($status >= 400 || ($code !== '' && $code !== 'SUCCESS')) {
            $message = (string) ($response['body']['message'] ?? '');
            throw new GowaRequestException("gowa recusou {$acao}: {$status} {$code} {$message}");
        }

        $results = $response['body']['results'] ?? null;

        return is_array($results) ? $results : [];
    }

    /**
     * @param array{status_code: int, body: array<string, mixed>} $response
     */
    private function sentResult(array $response, string $acao): SentMessage
    {
        $results = $this->results($response, $acao);
        $id = (string) ($results['message_id'] ?? $response['body']['results']['message_id'] ?? '');

        if ($id === '') {
            throw new GowaRequestException("gowa aceitou {$acao} sem devolver identificador da mensagem.");
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
            "O GOWA não envia mídia do tipo {$type->value} no formato ({$mime}).",
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
