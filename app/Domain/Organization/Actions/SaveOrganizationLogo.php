<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sur le disque "public" (comme SavePageBanner, T-072) : le logo doit être
 * atteignable par une URL directe pour l'affichage sur la page événement et
 * dans l'en-tête des e-mails.
 */
final class SaveOrganizationLogo
{
    public function handle(Organization $organization, UploadedFile $logo, User $user): Organization
    {
        Gate::forUser($user)->authorize('manageBranding', $organization);

        if ($organization->logo_path !== null) {
            Storage::disk('public')->delete($organization->logo_path);
        }

        $organization->update([
            'logo_path' => $logo->storeAs('organization-logos', Str::uuid().'.'.$logo->extension(), 'public'),
        ]);

        return $organization;
    }
}
