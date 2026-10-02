<?php

declare(strict_types=1);

namespace App\Support\Page;

use App\Domain\Event\Models\Event;
use App\Domain\Page\Models\PageBlockType;
use App\Domain\Page\Models\PageMedia;
use App\Support\Antivirus\FileScanStatus;

/**
 * Remplace, dans les blocs d'une page, le jeton d'un média déposé dans
 * Itaza par son adresse de lecture. Un média en attente d'analyse ou refusé
 * ne donne aucune adresse : le bloc retombe alors sur le lien extérieur, ou
 * disparaît (§7 du CLAUDE.md — seul un fichier sain est servi).
 *
 * Traverse Event et Page : sa place est dans Support (section 3 du
 * CLAUDE.md).
 */
final class ResolvePageMedia
{
    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    public function handle(array $blocks, Event $event): array
    {
        $tokens = [];

        foreach ($blocks as $block) {
            if ($block['type'] === PageBlockType::WelcomeMessage->value && ! empty($block['mediaToken'])) {
                $tokens[] = (string) $block['mediaToken'];
            }
        }

        if ($tokens === []) {
            return $blocks;
        }

        $media = PageMedia::query()
            ->where('event_id', $event->id)
            ->whereIn('token', $tokens)
            ->where('scan_status', FileScanStatus::Clean)
            ->get()
            ->keyBy('token');

        return array_map(function (array $block) use ($media, $event): array {
            $found = $media->get((string) ($block['mediaToken'] ?? ''));

            if (! $found instanceof PageMedia) {
                return $block;
            }

            return [
                ...$block,
                'url' => route('guest.registration.media', [
                    $event->organization->slug,
                    $event->slug,
                    $found->token,
                ]),
                'mediaKind' => $found->isAudio() ? 'audio' : 'video',
            ];
        }, $blocks);
    }
}
