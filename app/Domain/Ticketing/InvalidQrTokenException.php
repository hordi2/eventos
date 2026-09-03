<?php

declare(strict_types=1);

namespace App\Domain\Ticketing;

use RuntimeException;

/**
 * Le scan vérifie signature + expiration + statut + non-réutilisation
 * (§4.6 CLAUDE.md) — un facteur distinct par cas, pour un message d'erreur
 * clair côté check-in (T-060) plutôt qu'un « billet invalide » générique.
 */
final class InvalidQrTokenException extends RuntimeException
{
    public static function malformed(): self
    {
        return new self('Le jeton du billet est illisible ou mal formé.');
    }

    public static function invalidSignature(): self
    {
        return new self('La signature du billet est invalide.');
    }

    public static function expired(): self
    {
        return new self('Le billet a expiré.');
    }

    public static function revoked(): self
    {
        return new self('Ce billet a été réémis : cet exemplaire n\'est plus valide.');
    }

    public static function cancelled(): self
    {
        return new self('Ce billet a été annulé.');
    }

    public static function unknown(): self
    {
        return new self('Billet introuvable.');
    }
}
