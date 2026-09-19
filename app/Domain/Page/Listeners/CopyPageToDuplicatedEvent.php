<?php

declare(strict_types=1);

namespace App\Domain\Page\Listeners;

use App\Domain\Event\Data\EventDuplicationPart;
use App\Domain\Event\Events\EventDuplicated;
use App\Domain\Page\Models\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Recopie la page publique d'un événement dupliqué. La bannière est
 * dupliquée elle aussi : SavePageBanner supprime l'ancien fichier quand on
 * la remplace, un fichier partagé disparaîtrait des deux pages.
 */
final class CopyPageToDuplicatedEvent
{
    public function handle(EventDuplicated $duplication): void
    {
        if (! $duplication->includes(EventDuplicationPart::Page)) {
            return;
        }

        foreach ($duplication->eventIdMap as $sourceEventId => $copyEventId) {
            $page = Page::query()->where('event_id', $sourceEventId)->first();

            if ($page === null) {
                continue;
            }

            Page::query()->create([
                'organization_id' => $page->organization_id,
                'event_id' => $copyEventId,
                'banner_path' => $this->copyBanner($page->banner_path),
                'meta_description' => $page->meta_description,
                'program_items' => $page->program_items,
                'faq_items' => $page->faq_items,
            ]);
        }
    }

    private function copyBanner(?string $path): ?string
    {
        $disk = Storage::disk('public');

        if ($path === null || ! $disk->exists($path)) {
            return null;
        }

        $copy = 'page-banners/'.Str::uuid().'.'.pathinfo($path, PATHINFO_EXTENSION);
        $disk->copy($path, $copy);

        return $copy;
    }
}
