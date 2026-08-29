<?php

declare(strict_types=1);

namespace Gowa\Sdk\Dto;

use RuntimeException;

final class MediaUpload
{
    /**
     * @param resource|string $resourceOrPath
     */
    public function __construct(
        public readonly mixed $resourceOrPath,
        public readonly string $mimeType,
        public readonly string $filename,
    ) {}

    /**
     * @return resource
     */
    public function open()
    {
        if (is_resource($this->resourceOrPath)) {
            return $this->resourceOrPath;
        }

        if (is_string($this->resourceOrPath) && file_exists($this->resourceOrPath)) {
            $stream = fopen($this->resourceOrPath, 'r');
            if ($stream === false) {
                throw new RuntimeException("Não foi possível abrir o arquivo: {$this->resourceOrPath}");
            }
            return $stream;
        }

        throw new RuntimeException("Recurso de mídia inválido ou arquivo não encontrado.");
    }
}
