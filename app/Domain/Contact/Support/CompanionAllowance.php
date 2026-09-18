<?php

declare(strict_types=1);

namespace App\Domain\Contact\Support;

use App\Domain\Form\Support\FormSettings;
use InvalidArgumentException;

/**
 * Accompagnants qu'une invitation autorise (M3.5 « +X avec nombre défini par
 * invité »). « Illimité » est enregistré nul et reste plafonné, au moment de
 * répondre, par la limite de la plateforme.
 */
final class CompanionAllowance
{
    /**
     * @var list<string>
     */
    private const UNLIMITED_WORDS = ['illimite', 'illimité', 'illimitee', 'illimitée', 'unlimited', 'sans limite'];

    /**
     * Valeur saisie ou importée : vide ou 0 = aucun accompagnant, un nombre
     * jusqu'à la limite, ou « illimité ».
     *
     * @throws InvalidArgumentException
     */
    public static function parse(?string $raw): ?int
    {
        $value = mb_strtolower(trim((string) $raw));

        if ($value === '') {
            return 0;
        }

        if (in_array($value, self::UNLIMITED_WORDS, true)) {
            return null;
        }

        if (preg_match('/^\+?(\d{1,3})$/', $value, $matches) !== 1 || (int) $matches[1] > FormSettings::MAX_COMPANIONS) {
            throw new InvalidArgumentException(
                "Accompagnants autorisés : « {$raw} » n'est pas valable. Indiquez un nombre de 0 à ".FormSettings::MAX_COMPANIONS.', ou « illimité ».'
            );
        }

        return (int) $matches[1];
    }

    public static function label(?int $allowed): string
    {
        return match (true) {
            $allowed === null => 'Illimité',
            $allowed === 0 => 'Aucun',
            default => "+{$allowed}",
        };
    }
}
