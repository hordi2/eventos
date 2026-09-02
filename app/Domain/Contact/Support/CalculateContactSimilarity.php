<?php

declare(strict_types=1);

namespace App\Domain\Contact\Support;

use App\Domain\Contact\Models\Contact;

/**
 * Score de similarité 0-100 entre une ligne importée et un contact existant
 * (T-041, M3.3.2). Une correspondance d'e-mail exacte vaut 100 directement ;
 * sinon, similarité du nom complet (similar_text — aucune dépendance
 * supplémentaire nécessaire pour un premier import fonctionnel).
 */
final class CalculateContactSimilarity
{
    private const DUPLICATE_THRESHOLD = 85;

    /**
     * @param  array{first_name?: ?string, last_name?: ?string, email?: ?string}  $incoming
     */
    public function score(array $incoming, Contact $existing): int
    {
        $incomingEmail = isset($incoming['email']) ? mb_strtolower(trim((string) $incoming['email'])) : null;

        if ($incomingEmail !== null && $incomingEmail !== '' && $incomingEmail === $existing->email) {
            return 100;
        }

        $incomingName = trim(($incoming['first_name'] ?? '').' '.($incoming['last_name'] ?? ''));
        $existingName = trim(($existing->first_name ?? '').' '.($existing->last_name ?? ''));

        if ($incomingName === '' || $existingName === '') {
            return 0;
        }

        similar_text(mb_strtolower($incomingName), mb_strtolower($existingName), $percent);

        return (int) round($percent);
    }

    public function isDuplicate(int $score): bool
    {
        return $score >= self::DUPLICATE_THRESHOLD;
    }
}
