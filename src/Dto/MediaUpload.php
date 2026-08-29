<?php

declare(strict_types=1);

namespace Gowa\Sdk\Dto;

use RuntimeException;

final class MediaUpload
{
    /**
     * @param resource|string $source Stream resource, caminho de arquivo local ou URL externa (http/https)
     */
    public function __construct(
        public readonly mixed $source,
        public readonly string $mimeType,
        public readonly string $filename,
    ) {}

    /**
     * Cria um MediaUpload a partir de um caminho de arquivo local
     */
    public static function fromPath(string $path, ?string $mimeType = null, ?string $filename = null): self
    {
        if (! file_exists($path)) {
            throw new RuntimeException("Arquivo local não encontrado: {$path}");
        }

        $filename ??= basename($path);
        $mimeType ??= (function_exists('mime_content_type') ? mime_content_type($path) : false) ?: 'application/octet-stream';

        return new self($path, $mimeType, $filename);
    }

    /**
     * Cria um MediaUpload a partir de uma URL externa (http/https)
     */
    public static function fromUrl(string $url, ?string $mimeType = null, ?string $filename = null): self
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException("URL de mídia inválida: {$url}");
        }

        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $filename ??= basename($path) !== '' ? basename($path) : 'file';
        $mimeType ??= self::guessMimeFromFilename($filename);

        return new self($url, $mimeType, $filename);
    }

    /**
     * Cria um MediaUpload a partir de um resource de stream já aberto
     *
     * @param resource $stream
     */
    public static function fromStream(mixed $stream, string $mimeType, string $filename): self
    {
        if (! is_resource($stream)) {
            throw new RuntimeException("O parâmetro fornecido não é um resource de stream.");
        }

        return new self($stream, $mimeType, $filename);
    }

    /**
     * Abre o recurso como stream para envio no multipart
     *
     * @return resource
     */
    public function open()
    {
        if (is_resource($this->source)) {
            return $this->source;
        }

        if (is_string($this->source)) {
            if (file_exists($this->source) || filter_var($this->source, FILTER_VALIDATE_URL)) {
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 30,
                        'follow_location' => 1,
                        'header' => "User-Agent: gowa-php-sdk/1.0\r\n",
                    ],
                    'ssl' => [
                        'verify_peer' => true,
                        'verify_peer_name' => true,
                    ],
                ]);

                $stream = @fopen($this->source, 'r', false, $context);

                if ($stream === false) {
                    throw new RuntimeException("Não foi possível abrir o recurso de mídia: {$this->source}");
                }

                return $stream;
            }
        }

        throw new RuntimeException("Recurso de mídia inválido.");
    }

    private static function guessMimeFromFilename(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'mp4' => 'video/mp4',
            'm4a' => 'audio/m4a',
            'mp3' => 'audio/mp3',
            'ogg' => 'audio/ogg',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}
