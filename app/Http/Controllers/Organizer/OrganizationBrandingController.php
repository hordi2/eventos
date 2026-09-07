<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Organization\Actions\SaveOrganizationLogo;
use App\Domain\Organization\Actions\UpdateOrganizationBranding;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\ThemeMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Organization\UpdateBrandingRequest;
use App\Http\Requests\Organizer\Organization\UploadBrandingLogoRequest;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class OrganizationBrandingController extends Controller
{
    public function edit(): InertiaResponse
    {
        $organization = $this->currentOrganization();

        return Inertia::render('Organization/Branding', [
            'branding' => [
                'logo_url' => $organization->logo_path !== null ? Storage::disk('public')->url($organization->logo_path) : null,
                'primary_color' => $organization->primary_color,
                'theme_mode' => $organization->theme_mode->value,
            ],
        ]);
    }

    public function update(UpdateBrandingRequest $request, UpdateOrganizationBranding $action): JsonResponse
    {
        $organization = $action->handle(
            organization: $this->currentOrganization(),
            primaryColor: $request->string('primary_color')->toString() ?: null,
            themeMode: ThemeMode::from($request->string('theme_mode')->toString()),
            user: $request->user(),
        );

        return response()->json([
            'primary_color' => $organization->primary_color,
            'theme_mode' => $organization->theme_mode->value,
        ]);
    }

    public function uploadLogo(UploadBrandingLogoRequest $request, SaveOrganizationLogo $action): JsonResponse
    {
        $organization = $action->handle($this->currentOrganization(), $request->file('logo'), $request->user());

        return response()->json(['logo_url' => Storage::disk('public')->url($organization->logo_path)]);
    }

    private function currentOrganization(): Organization
    {
        return Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId());
    }
}
