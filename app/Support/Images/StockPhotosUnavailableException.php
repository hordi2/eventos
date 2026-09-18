<?php

declare(strict_types=1);

namespace App\Support\Images;

use RuntimeException;

/**
 * La bibliothèque de photos libres n'a pas pu répondre : clé absente, quota
 * atteint, réseau coupé. Le message est destiné à l'organisateur.
 */
final class StockPhotosUnavailableException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self("La bibliothèque de photos n'est pas encore configurée sur cette installation (clé Pexels manquante).");
    }

    public static function unreachable(): self
    {
        return new self('La bibliothèque de photos ne répond pas pour le moment. Réessayez dans quelques minutes.');
    }

    public static function notFound(): self
    {
        return new self("Cette photo n'est plus disponible dans la bibliothèque.");
    }
}
