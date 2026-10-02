<?php

declare(strict_types=1);

namespace App\Support\Guest;

/**
 * Polices de l'invitation. Trois rôles : les titres, le texte courant, et
 * l'écriture manuscrite — celle du « Save the date » des faire-part.
 *
 * Seules les familles réellement choisies sont chargées, et seulement dans
 * les graisses employées : la page invité doit tenir sous 500 Ko et
 * s'afficher en moins de deux secondes en 3G (§2 du CLAUDE.md).
 *
 * Une entrée sans adresse Google ne charge rien du tout : c'est le choix le
 * plus léger, et le seul qui fonctionne hors ligne.
 */
final class GuestFonts
{
    /**
     * @var array<string, array{label: string, stack: ?string, google: ?string, pdfFamily: ?string, pdfFile: ?string, pdfItalic: bool}>
     */
    public const HEADING = [
        'bodoni' => ['label' => 'Bodoni Moda', 'stack' => "'Bodoni Moda', ui-serif, serif", 'google' => 'Bodoni+Moda:ital,opsz,wght@0,6..96,400;0,6..96,500;1,6..96,400', 'pdfFamily' => "'Bodoni Moda', serif", 'pdfFile' => 'bodoni', 'pdfItalic' => true],
        'playfair' => ['label' => 'Playfair Display', 'stack' => "'Playfair Display', ui-serif, serif", 'google' => 'Playfair+Display:ital,wght@0,400;0,500;1,400', 'pdfFamily' => "'Playfair Display', serif", 'pdfFile' => 'playfair', 'pdfItalic' => true],
        'cormorant' => ['label' => 'Cormorant Garamond', 'stack' => "'Cormorant Garamond', ui-serif, serif", 'google' => 'Cormorant+Garamond:ital,wght@0,400;0,500;1,400', 'pdfFamily' => "'Cormorant Garamond', serif", 'pdfFile' => 'cormorant', 'pdfItalic' => true],
        'marcellus' => ['label' => 'Marcellus', 'stack' => "'Marcellus', ui-serif, serif", 'google' => 'Marcellus', 'pdfFamily' => "'Marcellus', serif", 'pdfFile' => 'marcellus', 'pdfItalic' => false],
        'georgia' => ['label' => 'Georgia', 'stack' => "Georgia, 'Times New Roman', serif", 'google' => null, 'pdfFamily' => 'serif', 'pdfFile' => null, 'pdfItalic' => false],
    ];

    /**
     * @var array<string, array{label: string, stack: ?string, google: ?string, pdfFamily: ?string, pdfFile: ?string, pdfItalic: bool}>
     */
    public const BODY = [
        'jakarta' => ['label' => 'Plus Jakarta Sans', 'stack' => "'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif", 'google' => 'Plus+Jakarta+Sans:wght@300;400;500', 'pdfFamily' => "'Plus Jakarta Sans', sans-serif", 'pdfFile' => 'jakarta', 'pdfItalic' => false],
        'jost' => ['label' => 'Jost', 'stack' => "'Jost', ui-sans-serif, sans-serif", 'google' => 'Jost:wght@300;400;500', 'pdfFamily' => "'Jost', sans-serif", 'pdfFile' => 'jost', 'pdfItalic' => false],
        'montserrat' => ['label' => 'Montserrat', 'stack' => "'Montserrat', ui-sans-serif, sans-serif", 'google' => 'Montserrat:wght@300;400;500', 'pdfFamily' => "'Montserrat', sans-serif", 'pdfFile' => 'montserrat', 'pdfItalic' => false],
        'systeme' => ['label' => "Police de l'appareil", 'stack' => "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif", 'google' => null, 'pdfFamily' => 'sans-serif', 'pdfFile' => null, 'pdfItalic' => false],
    ];

    /**
     * @var array<string, array{label: string, stack: ?string, google: ?string, pdfFamily: ?string, pdfFile: ?string, pdfItalic: bool}>
     */
    public const SCRIPT = [
        'great-vibes' => ['label' => 'Great Vibes', 'stack' => "'Great Vibes', cursive", 'google' => 'Great+Vibes', 'pdfFamily' => "'Great Vibes', serif", 'pdfFile' => 'great-vibes', 'pdfItalic' => false],
        'parisienne' => ['label' => 'Parisienne', 'stack' => "'Parisienne', cursive", 'google' => 'Parisienne', 'pdfFamily' => "'Parisienne', serif", 'pdfFile' => 'parisienne', 'pdfItalic' => false],
        'pinyon' => ['label' => 'Pinyon Script', 'stack' => "'Pinyon Script', cursive", 'google' => 'Pinyon+Script', 'pdfFamily' => "'Pinyon Script', serif", 'pdfFile' => 'pinyon', 'pdfItalic' => false],
        'dancing' => ['label' => 'Dancing Script', 'stack' => "'Dancing Script', cursive", 'google' => 'Dancing+Script:wght@400;500', 'pdfFamily' => "'Dancing Script', serif", 'pdfFile' => 'dancing', 'pdfItalic' => false],
        'aucune' => ['label' => 'Aucune, comme les titres', 'stack' => null, 'google' => null, 'pdfFamily' => null, 'pdfFile' => null, 'pdfItalic' => false],
    ];

    private const DEFAULTS = ['heading' => 'bodoni', 'body' => 'jakarta', 'script' => 'aucune'];

    /**
     * Les trois piles de polices à poser en variables CSS.
     *
     * @return array{heading: string, body: string, script: string}
     */
    public static function stacks(?string $heading, ?string $body, ?string $script): array
    {
        $headingStack = self::entry('heading', $heading)['stack'] ?? self::HEADING['bodoni']['stack'];

        return [
            'heading' => $headingStack,
            'body' => self::entry('body', $body)['stack'] ?? self::BODY['jakarta']['stack'],
            // « Aucune » : le mot manuscrit prend la police des titres.
            'script' => self::entry('script', $script)['stack'] ?? $headingStack,
        ];
    }

    /**
     * Adresse de la feuille de style Google Fonts pour les seules familles
     * choisies. Null quand aucune n'a besoin d'être chargée — un choix
     * comme Georgia ou « police de l'appareil » n'en réclame aucune.
     */
    public static function stylesheet(?string $heading, ?string $body, ?string $script): ?string
    {
        $families = array_values(array_unique(array_filter([
            self::entry('heading', $heading)['google'],
            self::entry('body', $body)['google'],
            self::entry('script', $script)['google'],
        ], fn (?string $family): bool => $family !== null)));

        if ($families === []) {
            return null;
        }

        return 'https://fonts.googleapis.com/css2?family='.implode('&family=', $families).'&display=swap';
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(string $role): array
    {
        $catalogue = self::catalogue($role);

        return array_map(
            fn (string $key, array $font): array => ['value' => $key, 'label' => $font['label']],
            array_keys($catalogue),
            $catalogue,
        );
    }

    /**
     * Le choix de l'organisateur, ou celui d'origine quand il est inconnu :
     * une police retirée du catalogue ne doit pas casser la page.
     *
     * @return array{label: string, stack: ?string, google: ?string, pdfFamily: ?string, pdfFile: ?string, pdfItalic: bool}
     */
    private static function entry(string $role, ?string $key): array
    {
        $catalogue = self::catalogue($role);

        return $catalogue[$key ?? ''] ?? $catalogue[self::DEFAULTS[$role]];
    }

    /**
     * @return array<string, array{label: string, stack: ?string, google: ?string, pdfFamily: ?string, pdfFile: ?string, pdfItalic: bool}>
     */
    private static function catalogue(string $role): array
    {
        return match ($role) {
            'heading' => self::HEADING,
            'body' => self::BODY,
            default => self::SCRIPT,
        };
    }
}
