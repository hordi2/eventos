<?php

declare(strict_types=1);

namespace App\Domain\Form\Support;

use Illuminate\Validation\Rule;

/**
 * Réglages de présentation d'un formulaire (colonne forms.settings) : écran
 * d'accueil, réponse « Je ne peux pas venir », écrans de confirmation et de
 * refus, thème du parcours invité.
 *
 * Toute lecture passe par resolve() : un formulaire créé avant ces réglages
 * (settings null) garde exactement le parcours d'avant — pas d'accueil, pas
 * de refus, thème par défaut.
 */
final class FormSettings
{
    /**
     * Polices proposées : uniquement des familles déjà chargées par les pages
     * invité ou disponibles sur l'appareil, pour ne jamais alourdir la page
     * (contrainte < 500 Ko du CLAUDE.md) avec une police téléchargée.
     *
     * @var array<string, array{label: string, stack: string}>
     */
    public const FONTS = [
        'jakarta' => ['label' => 'Plus Jakarta Sans', 'stack' => "'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif"],
        'bodoni' => ['label' => 'Bodoni Moda', 'stack' => "'Bodoni Moda', ui-serif, serif"],
        'grotesk' => ['label' => 'Space Grotesk', 'stack' => "'Space Grotesk', ui-sans-serif, sans-serif"],
        'georgia' => ['label' => 'Georgia', 'stack' => "Georgia, 'Times New Roman', serif"],
        'systeme' => ['label' => "Police de l'appareil", 'stack' => "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif"],
    ];

    /**
     * Type d'image du thème => clé de réglage où son chemin est rangé.
     *
     * @var array<string, string>
     */
    public const IMAGE_KINDS = [
        'logo' => 'logo_path',
        'background' => 'background_image_path',
    ];

    /**
     * Nombre maximal d'accompagnants qu'un organisateur peut autoriser par
     * inscription (T-032).
     */
    public const MAX_COMPANIONS = 20;

    private const COLOR_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    /**
     * @var array<string, array<string, mixed>>
     */
    private const DEFAULTS = [
        'welcome' => [
            'enabled' => false,
            'title' => '',
            'message' => '',
            'button_label' => 'Commencer',
        ],
        'rsvp' => [
            'decline_enabled' => false,
            'attending_label' => 'Je serai présent(e)',
            'decline_label' => 'Je ne peux pas venir',
            // 0 : l'invité s'inscrit seul, comme avant les accompagnants.
            'max_companions' => 0,
        ],
        'confirmation' => [
            'title' => '',
            'message' => '',
        ],
        'decline_screen' => [
            'title' => 'Merci pour votre réponse',
            'message' => 'Nous regrettons que vous ne puissiez pas être des nôtres.',
        ],
        'theme' => [
            'background_color' => null,
            'text_color' => null,
            'accent_color' => null,
            'button_color' => null,
            'button_text_color' => null,
            'heading_font' => 'bodoni',
            'body_font' => 'jakarta',
            'logo_path' => null,
            'background_image_path' => null,
        ],
    ];

    /**
     * Chaque clé connue avec sa valeur enregistrée, ou sa valeur par défaut.
     * Une clé inconnue est ignorée.
     *
     * @param  array<string, mixed>|null  $settings
     * @return array<string, array<string, mixed>>
     */
    public static function resolve(?array $settings): array
    {
        $resolved = self::DEFAULTS;

        foreach (self::DEFAULTS as $section => $defaults) {
            $given = $settings[$section] ?? null;

            if (! is_array($given)) {
                continue;
            }

            foreach (array_keys($defaults) as $key) {
                if (array_key_exists($key, $given)) {
                    $resolved[$section][$key] = $given[$key];
                }
            }
        }

        return $resolved;
    }

    /**
     * Les chemins d'image n'y figurent pas : seul l'envoi d'une image
     * (SaveFormThemeImage) peut les écrire.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(string $prefix = 'settings'): array
    {
        $color = ['nullable', 'string', 'regex:'.self::COLOR_PATTERN];
        $font = ['nullable', 'string', Rule::in(array_keys(self::FONTS))];

        return [
            $prefix => ['nullable', 'array'],
            "{$prefix}.welcome.enabled" => ['sometimes', 'boolean'],
            "{$prefix}.welcome.title" => ['nullable', 'string', 'max:120'],
            "{$prefix}.welcome.message" => ['nullable', 'string', 'max:2000'],
            "{$prefix}.welcome.button_label" => ['nullable', 'string', 'max:40'],
            "{$prefix}.rsvp.decline_enabled" => ['sometimes', 'boolean'],
            "{$prefix}.rsvp.attending_label" => ['nullable', 'string', 'max:80'],
            "{$prefix}.rsvp.decline_label" => ['nullable', 'string', 'max:80'],
            "{$prefix}.rsvp.max_companions" => ['sometimes', 'integer', 'min:0', 'max:'.self::MAX_COMPANIONS],
            "{$prefix}.confirmation.title" => ['nullable', 'string', 'max:120'],
            "{$prefix}.confirmation.message" => ['nullable', 'string', 'max:2000'],
            "{$prefix}.decline_screen.title" => ['nullable', 'string', 'max:120'],
            "{$prefix}.decline_screen.message" => ['nullable', 'string', 'max:2000'],
            "{$prefix}.theme.background_color" => $color,
            "{$prefix}.theme.text_color" => $color,
            "{$prefix}.theme.accent_color" => $color,
            "{$prefix}.theme.button_color" => $color,
            "{$prefix}.theme.button_text_color" => $color,
            "{$prefix}.theme.heading_font" => $font,
            "{$prefix}.theme.body_font" => $font,
        ];
    }

    /**
     * Variables CSS du parcours invité. Uniquement des couleurs revérifiées
     * et des piles de polices de la liste fermée : aucune valeur libre ne
     * peut atteindre la feuille de style d'une page publique.
     *
     * @param  array<string, array<string, mixed>>  $resolved
     * @return array<string, string>
     */
    public static function cssVariables(array $resolved): array
    {
        $theme = $resolved['theme'];
        $colors = [
            '--form-background' => 'background_color',
            '--color-ink' => 'text_color',
            '--color-accent' => 'accent_color',
            '--form-button' => 'button_color',
            '--form-button-text' => 'button_text_color',
        ];

        $variables = [];

        foreach ($colors as $variable => $key) {
            $value = $theme[$key] ?? null;

            if (is_string($value) && preg_match(self::COLOR_PATTERN, $value) === 1) {
                $variables[$variable] = $value;
            }
        }

        $variables['--font-serif'] = self::FONTS[(string) $theme['heading_font']]['stack'] ?? self::FONTS['bodoni']['stack'];
        $variables['--font-sans'] = self::FONTS[(string) $theme['body_font']]['stack'] ?? self::FONTS['jakarta']['stack'];

        return $variables;
    }
}
