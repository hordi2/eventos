<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Actions;

use App\Domain\CheckIn\Models\BadgeSettings;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * « Éditeur simple de badge » (T-064) réduit au logo pour le MVP (voir le
 * docblock de la migration badge_settings). Remplace le logo précédent
 * plutôt que d'en accumuler : un seul logo par événement.
 */
final class SaveBadgeLogo
{
    public function handle(Organization $organization, int $eventId, UploadedFile $logo, User $user): BadgeSettings
    {
        Gate::forUser($user)->authorize('checkIn', $organization);

        $settings = BadgeSettings::query()->where('event_id', $eventId)->first();

        if ($settings?->logo_path !== null) {
            Storage::disk('local')->delete($settings->logo_path);
        }

        $path = $logo->storeAs('badge-logos', Str::uuid().'.'.$logo->extension(), 'local');

        if ($settings === null) {
            return BadgeSettings::query()->create([
                'organization_id' => $organization->id,
                'event_id' => $eventId,
                'logo_path' => $path,
            ]);
        }

        $settings->update(['logo_path' => $path]);

        return $settings;
    }
}
