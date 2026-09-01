<?php

declare(strict_types=1);

/**
 * Vérifie par le calcul (formule WCAG 2.1) que chaque paire texte/fond
 * effectivement utilisée dans l'interface respecte le contraste minimum
 * AA (4,5:1 pour du texte normal, 3:1 pour du texte large ≥ 18px ou
 * ≥ 14px en gras). Les couleurs sont dupliquées ici depuis
 * resources/css/app.css : en cas de changement de palette, ce test doit
 * être mis à jour en même temps.
 */
function relativeLuminance(string $hex): float
{
    [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');

    $channel = function (int $c): float {
        $c /= 255;

        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    };

    return 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
}

function contrastRatio(string $hex1, string $hex2): float
{
    $l1 = relativeLuminance($hex1);
    $l2 = relativeLuminance($hex2);
    [$lighter, $darker] = $l1 > $l2 ? [$l1, $l2] : [$l2, $l1];

    return ($lighter + 0.05) / ($darker + 0.05);
}

dataset('paires_texte_normal_clair', [
    'ink sur bg' => ['#1B1611', '#FFFFFF'],
    'ink-soft sur bg' => ['#6D655C', '#FFFFFF'],
    'ink-soft sur bg-alt' => ['#6D655C', '#F4F4F2'],
    'ink-soft sur bg-deep' => ['#6D655C', '#ECECE9'],
    'ink sur bg-alt' => ['#1B1611', '#F4F4F2'],
    'blanc sur ink (bouton)' => ['#FFFFFF', '#1B1611'],
    'blanc sur accent (bouton)' => ['#FFFFFF', '#0A0A0A'],
    'danger sur danger-bg (alerte)' => ['#C0392B', '#FDF1EF'],
    'success sur success-bg (alerte)' => ['#1A6E42', '#EEF8F2'],
]);

it('respecte le contraste AA (4,5:1) en thème clair', function (string $fg, string $bg): void {
    expect(contrastRatio($fg, $bg))->toBeGreaterThanOrEqual(4.5);
})->with('paires_texte_normal_clair');

dataset('paires_texte_normal_sombre', [
    'ink sur bg' => ['#F5F3F0', '#14110D'],
    'ink-soft sur bg' => ['#B7AC9D', '#14110D'],
    'ink-soft sur bg-alt' => ['#B7AC9D', '#1C1912'],
    'ink-soft sur bg-deep' => ['#B7AC9D', '#262119'],
    'ink sur accent (bouton, texte foncé sur bouton clair)' => ['#14110D', '#F5F3F0'],
    'danger sur danger-bg (alerte)' => ['#FF6B57', '#2A1613'],
    'success sur success-bg (alerte)' => ['#4ADE80', '#0F2318'],
]);

it('respecte le contraste AA (4,5:1) en thème sombre', function (string $fg, string $bg): void {
    expect(contrastRatio($fg, $bg))->toBeGreaterThanOrEqual(4.5);
})->with('paires_texte_normal_sombre');
