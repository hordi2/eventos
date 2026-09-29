<?php

declare(strict_types=1);

namespace App\Support\Guest;

/**
 * Langues proposées aux invités (lot 2). Ajouter une langue tient en deux
 * gestes : une entrée ici et un fichier lang/{code}.json — aucune vue à
 * retoucher, le texte français sert de clé de traduction.
 */
final class GuestLocales
{
    public const SUPPORTED = [
        'fr' => 'Français',
        'en' => 'English',
    ];

    public static function supports(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, self::SUPPORTED);
    }

    /**
     * Première langue du navigateur que nous savons parler, s'il y en a une.
     *
     * @param  list<string>  $browserLanguages  en-tête Accept-Language, du plus au moins souhaité
     */
    public static function preferred(array $browserLanguages): ?string
    {
        foreach ($browserLanguages as $language) {
            $code = mb_strtolower(mb_substr(str_replace('_', '-', $language), 0, 2));

            if (self::supports($code)) {
                return $code;
            }
        }

        return null;
    }
}
