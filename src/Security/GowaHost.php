<?php

declare(strict_types=1);

namespace Gowa\Sdk\Security;

use Gowa\Sdk\Exceptions\GowaSecurityException;

final class GowaHost
{
    public static function assertBelongsToServer(string $url, string $baseUrl): void
    {
        $target = parse_url($url);
        $server = parse_url($baseUrl);

        $targetHost = is_array($target) ? ($target['host'] ?? null) : null;
        $serverHost = is_array($server) ? ($server['host'] ?? null) : null;

        // Caminho relativo é do próprio servidor por construção
        if ($targetHost === null) {
            return;
        }

        if (! is_string($serverHost) || $serverHost === '' || $targetHost !== $serverHost) {
            throw new GowaSecurityException(
                "Recusando buscar fora do servidor gowa: {$url}",
            );
        }
    }
}
