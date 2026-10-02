<?php

declare(strict_types=1);

namespace App\Domain\Page\Support;

use App\Domain\Page\Models\Page;
use App\Domain\Page\Models\PageBlockType;
use Illuminate\Support\Str;

/**
 * Blocs de la page publique : ce qui est enregistré, et ce qu'il faut
 * afficher pour une page qui n'en a pas encore.
 */
final class PageBlocks
{
    /**
     * Blocs à afficher. Une page jamais ouverte depuis le constructeur
     * n'en a aucun : elle garde alors sa mise en page d'origine —
     * programme, lieu, questions fréquentes —, sans quoi elle perdrait
     * son contenu du jour au lendemain.
     *
     * @return list<array<string, mixed>>
     */
    public static function resolve(?Page $page): array
    {
        $stored = $page?->blocks;

        if (is_array($stored) && $stored !== []) {
            return self::order(self::normalize($stored));
        }

        return self::order(self::legacy($page));
    }

    /**
     * Un bloc resté vide ne devient pas un feuillet : il occuperait un
     * écran entier sur la page, et une page blanche dans le faire-part.
     * Les blocs qui tirent leur contenu de l'événement lui-même (lieu,
     * intervenants, sessions) se jugent à l'affichage, pas ici.
     *
     * @param  array<string, mixed>  $block
     */
    public static function hasContent(array $block): bool
    {
        $type = PageBlockType::tryFrom((string) $block['type']);

        return match ($type) {
            PageBlockType::Text, PageBlockType::SaveTheDate => self::text($block['body'] ?? null) !== null
                || self::text($block['title'] ?? null) !== null,
            PageBlockType::Image, PageBlockType::FullPhoto => self::text($block['path'] ?? null) !== null,
            PageBlockType::Video => self::text($block['url'] ?? null) !== null,
            PageBlockType::WelcomeMessage => self::text($block['url'] ?? null) !== null
                || self::text($block['mediaToken'] ?? null) !== null,
            PageBlockType::Program, PageBlockType::Faq, PageBlockType::Details => ($block['items'] ?? []) !== [],
            PageBlockType::Gallery => array_filter(
                $block['items'] ?? [],
                fn (array $item): bool => self::text($item['path'] ?? null) !== null,
            ) !== [],
            default => true,
        };
    }

    /**
     * L'ordre d'affichage, le même sur la page web et sur le faire-part :
     * le code d'entrée, puis la confirmation de présence, ferment toujours
     * l'invitation. C'est la dernière chose qu'on lit, et la dernière page
     * qu'on imprime ; le reste garde l'ordre voulu par l'organisateur.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    public static function order(array $blocks): array
    {
        $closing = [PageBlockType::EntryQr->value, PageBlockType::Rsvp->value];
        $ordered = array_values(array_filter(
            $blocks,
            fn (array $block): bool => ! in_array($block['type'], $closing, true),
        ));

        foreach ($closing as $type) {
            foreach ($blocks as $block) {
                if ($block['type'] === $type) {
                    $ordered[] = $block;
                }
            }
        }

        return $ordered;
    }

    /**
     * Les deux derniers feuillets de l'invitation, ajoutés quand
     * l'organisateur ne les a pas composés lui-même : le code d'entrée,
     * puis la confirmation de présence. Une invitation se termine toujours
     * par « serez-vous des nôtres ? », sur l'écran comme sur le papier.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    public static function withClosingSheets(array $blocks): array
    {
        $blocks = array_values(array_filter($blocks, self::hasContent(...)));

        foreach ([PageBlockType::EntryQr, PageBlockType::Rsvp] as $type) {
            $present = array_filter($blocks, fn (array $block): bool => $block['type'] === $type->value);

            if ($present === []) {
                $blocks[] = [
                    'id' => 'itaza-'.$type->value,
                    'type' => $type->value,
                    'title' => null,
                    ...self::background([]),
                    ...self::payload($type, []),
                ];
            }
        }

        return self::order($blocks);
    }

    /**
     * Ne garde que les blocs connus, avec leurs seules valeurs utiles :
     * ce qui arrive du navigateur n'est jamais recopié tel quel.
     *
     * @param  array<int, mixed>  $blocks
     * @return list<array<string, mixed>>
     */
    public static function normalize(array $blocks): array
    {
        $normalized = [];

        foreach ($blocks as $block) {
            if (! is_array($block) || ! is_string($block['type'] ?? null)) {
                continue;
            }

            $type = PageBlockType::tryFrom($block['type']);

            if ($type === null) {
                continue;
            }

            $normalized[] = [
                'id' => is_string($block['id'] ?? null) && $block['id'] !== '' ? $block['id'] : (string) Str::uuid(),
                'type' => $type->value,
                'title' => self::text($block['title'] ?? null),
                ...self::background($block),
                ...self::payload($type, $block),
            ];
        }

        return $normalized;
    }

    /**
     * Fond d'un bloc, commun à tous les types : une image de la
     * bibliothèque, l'épaisseur du voile posé dessus, et la couleur du
     * texte — clair sur une photo sombre, sombre sur une photo claire.
     *
     * @param  array<string, mixed>  $block
     * @return array{background: ?string, backgroundColor: ?string, backgroundOverlay: int, textTone: string}
     */
    private static function background(array $block): array
    {
        $tone = $block['textTone'] ?? null;
        $color = self::text($block['backgroundColor'] ?? null);

        return [
            'background' => self::text($block['background'] ?? null),
            // Couleur revérifiée : seul un code hexadécimal entre dans le
            // style de la page.
            'backgroundColor' => $color !== null && preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? $color : null,
            'backgroundOverlay' => max(0, min(90, is_numeric($block['backgroundOverlay'] ?? null) ? (int) $block['backgroundOverlay'] : 45)),
            'textTone' => $tone === 'light' ? 'light' : 'dark',
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private static function payload(PageBlockType $type, array $block): array
    {
        return match ($type) {
            PageBlockType::Text => ['body' => self::text($block['body'] ?? null) ?? ''],
            PageBlockType::Image => ['path' => self::text($block['path'] ?? null), 'alt' => self::text($block['alt'] ?? null)],
            PageBlockType::Video => ['url' => self::text($block['url'] ?? null)],
            PageBlockType::Program => [
                // Les illustrations s'affichent, ou pas : c'est l'organisateur
                // qui tranche, d'un interrupteur.
                'showIcons' => (bool) ($block['showIcons'] ?? false),
                // path : l'illustration déposée par l'organisateur, qui
                // remplace alors le dessin au trait.
                'items' => self::items($block['items'] ?? null, ['time', 'title', 'description', 'icon', 'path']),
            ],
            PageBlockType::Faq => ['items' => self::items($block['items'] ?? null, ['question', 'answer'])],
            PageBlockType::SaveTheDate => ['body' => self::text($block['body'] ?? null) ?? ''],
            // Une carte : son intitulé (title), sa valeur (time) et sa note.
            PageBlockType::Details => ['items' => self::items($block['items'] ?? null, ['title', 'time', 'description'])],
            // mediaToken : le fichier déposé dans Itaza, qui passe devant
            // l'adresse extérieure quand les deux sont renseignés.
            PageBlockType::WelcomeMessage => [
                'url' => self::text($block['url'] ?? null),
                'mediaToken' => self::text($block['mediaToken'] ?? null),
                'mediaName' => self::text($block['mediaName'] ?? null),
                'body' => self::text($block['body'] ?? null) ?? '',
            ],
            PageBlockType::EntryQr => ['body' => self::text($block['body'] ?? null) ?? ''],
            // Les trois réponses se formulent comme l'organisateur l'entend.
            PageBlockType::Rsvp => [
                'body' => self::text($block['body'] ?? null) ?? '',
                'yesLabel' => self::text($block['yesLabel'] ?? null),
                'noLabel' => self::text($block['noLabel'] ?? null),
                'laterLabel' => self::text($block['laterLabel'] ?? null),
            ],
            // Une photo de la galerie : son chemin dans la bibliothèque et sa légende.
            PageBlockType::Gallery => ['items' => self::items($block['items'] ?? null, ['path', 'description'])],
            PageBlockType::FullPhoto => ['path' => self::text($block['path'] ?? null), 'body' => self::text($block['body'] ?? null) ?? ''],
            PageBlockType::Venue, PageBlockType::Countdown, PageBlockType::Speakers,
            PageBlockType::Sessions, PageBlockType::GuestBook => [],
        };
    }

    /**
     * @param  list<string>  $keys
     * @return list<array<string, string|null>>
     */
    private static function items(mixed $items, array $keys): array
    {
        if (! is_array($items)) {
            return [];
        }

        $rows = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $row = [];

            foreach ($keys as $key) {
                $row[$key] = self::text($item[$key] ?? null);
            }

            // Une ligne entièrement vide n'a rien à faire sur la page.
            if (array_filter($row) !== []) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private static function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return trim($value) === '' ? null : trim($value);
    }

    /**
     * Mise en page d'origine, dans l'ordre où elle s'affichait.
     *
     * @return list<array<string, mixed>>
     */
    private static function legacy(?Page $page): array
    {
        $blocks = [];

        if ($page !== null && $page->program_items !== []) {
            $blocks[] = ['id' => 'legacy-program', 'type' => PageBlockType::Program->value, 'title' => null, 'items' => $page->program_items];
        }

        $blocks[] = ['id' => 'legacy-venue', 'type' => PageBlockType::Venue->value, 'title' => null];

        if ($page !== null && $page->faq_items !== []) {
            $blocks[] = ['id' => 'legacy-faq', 'type' => PageBlockType::Faq->value, 'title' => null, 'items' => $page->faq_items];
        }

        return $blocks;
    }
}
