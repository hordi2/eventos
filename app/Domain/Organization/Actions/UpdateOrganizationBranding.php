<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\ThemeMode;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class UpdateOrganizationBranding
{
    public function handle(Organization $organization, ?string $primaryColor, ThemeMode $themeMode, User $user): Organization
    {
        Gate::forUser($user)->authorize('manageBranding', $organization);

        $organization->update([
            'primary_color' => $primaryColor,
            'theme_mode' => $themeMode,
        ]);

        return $organization;
    }
}
