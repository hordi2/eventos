<?php

declare(strict_types=1);

namespace App\Domain\Form\Support;

/**
 * CSS personnalisé du parcours invité (plans payants). Il finit sur une page
 * publique : les constructions qui font sortir la feuille de son rôle sont
 * refusées à l'enregistrement, et revérifiées à la livraison — une feuille
 * enregistrée avant une règle plus stricte, ou écrite directement en base, ne
 * doit pas atteindre un invité.
 *
 * Ce qui est refusé, et pourquoi :
 * - @import, @charset, @namespace : feraient charger une feuille extérieure ;
 * - url() ailleurs que dans /storage : sortie de données vers un tiers ;
 * - expression(), behavior, -moz-binding : exécution de code sur de vieux
 *   navigateurs ;
 * - « < » : rien de légitime en CSS, et couperait court à toute tentative de
 *   fermer la balise <style> si la feuille était un jour servie en ligne.
 */
final class CustomCss
{
    public const MAX_LENGTH = 5000;

    /**
     * @var array<string, string>
     */
    private const FORBIDDEN = [
        '/@import\b/i' => '@import',
        '/@charset\b/i' => '@charset',
        '/@namespace\b/i' => '@namespace',
        '/expression\s*\(/i' => 'expression(',
        '/behaviou?r\s*:/i' => 'behavior',
        '/-moz-binding\s*:/i' => '-moz-binding',
        '/</' => 'le caractère « < »',
    ];

    private const ALLOWED_URL_PREFIX = '/storage/';

    /**
     * Ce qui, dans cette feuille, ne peut pas être publié.
     *
     * @return list<string>
     */
    public static function problems(string $css): array
    {
        $css = self::withoutComments($css);
        $problems = [];

        foreach (self::FORBIDDEN as $pattern => $label) {
            if (preg_match($pattern, $css) === 1) {
                $problems[] = $label;
            }
        }

        preg_match_all('/url\(\s*([^)]*)\)/i', $css, $matches);

        foreach ($matches[1] as $target) {
            if (! str_starts_with(trim($target, " \t\n\r\"'"), self::ALLOWED_URL_PREFIX)) {
                $problems[] = 'une adresse extérieure dans url() — les images se choisissent dans « Mes images »';

                break;
            }
        }

        return array_values(array_unique($problems));
    }

    /**
     * La feuille telle qu'elle est servie à l'invité : vide si elle n'a rien à
     * dire, ou si elle contient ce que problems() refuse.
     */
    public static function forDelivery(mixed $css): string
    {
        if (! is_string($css) || trim($css) === '' || strlen($css) > self::MAX_LENGTH) {
            return '';
        }

        if (self::problems($css) !== []) {
            return '';
        }

        return trim(self::withoutComments($css));
    }

    /**
     * Les commentaires cachent aussi bien un mot interdit qu'une note : on les
     * retire avant toute vérification, et ils ne sont donc jamais livrés.
     */
    private static function withoutComments(string $css): string
    {
        return (string) preg_replace('#/\*.*?\*/#s', '', $css);
    }
}
