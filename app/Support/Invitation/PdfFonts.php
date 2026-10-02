<?php

declare(strict_types=1);

namespace App\Support\Invitation;

use App\Support\Guest\GuestFonts;

/**
 * Les polices de l'invitation, pour le papier. Le faire-part doit se lire
 * dans les mêmes lettres que la page web : les fichiers sont embarqués dans
 * le dépôt (resources/fonts) et déclarés en @font-face, dompdf ne sachant
 * rien chercher sur le réseau — et n'ayant pas à le faire.
 *
 * Une police dont le fichier manque retombe sur celle que dompdf embarque
 * (un Times, un Helvetica) : le faire-part sort toujours, même amoindri.
 */
final class PdfFonts
{
    private const DIRECTORY = 'fonts';

    /**
     * Les trois familles à écrire dans le style du faire-part.
     *
     * @return array{heading: string, body: string, script: string}
     */
    public static function families(?string $heading, ?string $body, ?string $script): array
    {
        $headingFamily = self::family('heading', $heading) ?? 'serif';

        return [
            'heading' => $headingFamily,
            'body' => self::family('body', $body) ?? 'sans-serif',
            // « Aucune » : le mot manuscrit prend la police des titres.
            'script' => self::family('script', $script) ?? $headingFamily,
        ];
    }

    /**
     * Les déclarations @font-face des seules familles choisies.
     */
    public static function faces(?string $heading, ?string $body, ?string $script): string
    {
        $faces = '';

        foreach ([['heading', $heading], ['body', $body], ['script', $script]] as [$role, $key]) {
            $font = self::entry($role, $key);

            if ($font['pdfFile'] === null || $font['pdfFamily'] === null) {
                continue;
            }

            $name = trim(explode(',', $font['pdfFamily'])[0], " '");
            $faces .= self::face($name, $font['pdfFile'], 'regular', 'normal');

            if ($font['pdfItalic']) {
                $faces .= self::face($name, $font['pdfFile'], 'italic', 'italic');
            }
        }

        return $faces;
    }

    private static function face(string $name, string $file, string $suffix, string $style): string
    {
        $path = resource_path(self::DIRECTORY."/{$file}-{$suffix}.ttf");

        if (! is_file($path)) {
            return '';
        }

        return "@font-face { font-family: '{$name}'; font-style: {$style}; font-weight: 400; src: url('{$path}') format('truetype'); }\n";
    }

    private static function family(string $role, ?string $key): ?string
    {
        $font = self::entry($role, $key);

        if ($font['pdfFamily'] === null) {
            return null;
        }

        // Fichier manquant : inutile de nommer une famille que dompdf ne
        // saura pas charger, il vaut mieux sa police d'origine.
        if ($font['pdfFile'] !== null && ! is_file(resource_path(self::DIRECTORY."/{$font['pdfFile']}-regular.ttf"))) {
            return null;
        }

        return $font['pdfFamily'];
    }

    /**
     * @return array{label: string, stack: ?string, google: ?string, pdfFamily: ?string, pdfFile: ?string, pdfItalic: bool}
     */
    private static function entry(string $role, ?string $key): array
    {
        $catalogue = match ($role) {
            'heading' => GuestFonts::HEADING,
            'body' => GuestFonts::BODY,
            default => GuestFonts::SCRIPT,
        };

        $default = match ($role) {
            'heading' => 'bodoni',
            'body' => 'jakarta',
            default => 'aucune',
        };

        return $catalogue[$key ?? ''] ?? $catalogue[$default];
    }
}
