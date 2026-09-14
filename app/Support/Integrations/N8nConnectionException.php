<?php

declare(strict_types=1);

namespace App\Support\Integrations;

use RuntimeException;

final class N8nConnectionException extends RuntimeException
{
    public static function unreachable(string $baseUrl): self
    {
        return new self("Impossible de joindre l'instance n8n à l'adresse {$baseUrl}. Vérifiez l'URL et que l'instance est accessible depuis Internet.");
    }

    public static function invalidKey(): self
    {
        return new self('La clé API n8n a été refusée. Recréez-la depuis Réglages → n8n API dans votre instance.');
    }

    public static function unexpectedStatus(int $status): self
    {
        return new self("L'instance n8n a répondu une erreur HTTP {$status}.");
    }
}
