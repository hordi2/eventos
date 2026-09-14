<?php

declare(strict_types=1);

namespace App\Domain\Form\Support;

/**
 * Ordre de lecture d'une adresse postale. PostgreSQL (jsonb) range les clés
 * d'un objet à sa façon : l'ordre d'enregistrement d'une réponse est perdu,
 * il faut donc toujours le reconstruire à partir de cette liste.
 */
final class PostalAddress
{
    /**
     * @var list<string>
     */
    public const PARTS = ['line1', 'line2', 'city', 'region', 'postal_code', 'country'];

    /**
     * @param  array<array-key, mixed>  $address
     */
    public static function format(array $address): string
    {
        $parts = [];

        foreach (self::PARTS as $part) {
            $value = $address[$part] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $parts[] = trim($value);
            }
        }

        return implode(', ', $parts);
    }
}
