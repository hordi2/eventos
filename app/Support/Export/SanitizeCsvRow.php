<?php

declare(strict_types=1);

namespace App\Support\Export;

/**
 * Neutralise l'injection de formule CSV (OWASP) : un champ qui commence
 * par =, +, -, @, une tabulation ou un retour chariot s'exécute comme une
 * formule à l'ouverture du fichier dans Excel/Sheets — et ces champs
 * viennent souvent d'un invité (nom, réponse libre de formulaire), jamais
 * uniquement d'un organisateur de confiance.
 */
final class SanitizeCsvRow
{
    private const DANGEROUS_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * @param  array<int|string, mixed>  $row
     * @return array<int|string, mixed>
     */
    public static function handle(array $row): array
    {
        return array_map(self::sanitizeCell(...), $row);
    }

    private static function sanitizeCell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return in_array($value[0], self::DANGEROUS_PREFIXES, true) ? "'{$value}" : $value;
    }
}
