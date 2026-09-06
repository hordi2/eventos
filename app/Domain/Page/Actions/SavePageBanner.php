<?php

declare(strict_types=1);

namespace App\Domain\Page\Actions;

use App\Domain\Organization\Models\Organization;
use App\Domain\Page\Models\Page;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sur le disque "public" (contrairement à SaveBadgeLogo, sur "local") : une
 * bannière doit être atteignable directement par une URL publique pour
 * l'affichage, l'image de partage (og:image) et schema.org/Event.image —
 * un logo de badge, lui, n'est jamais servi qu'en interne pour le PDF.
 */
final class SavePageBanner
{
    public function handle(Organization $organization, int $eventId, UploadedFile $banner, User $user): Page
    {
        Gate::forUser($user)->authorize('updateEvents', $organization);

        $page = Page::query()->where('event_id', $eventId)->first();

        if ($page?->banner_path !== null) {
            Storage::disk('public')->delete($page->banner_path);
        }

        $path = $banner->storeAs('page-banners', Str::uuid().'.'.$banner->extension(), 'public');

        return Page::query()->updateOrCreate(
            ['event_id' => $eventId],
            ['organization_id' => $organization->id, 'banner_path' => $path],
        );
    }
}
