<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer\Settings;

use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class ReferralController extends Controller
{
    public function edit(): Response
    {
        $organization = Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId());

        // Rattrapage pour les organisations créées avant ce chantier
        // (RegisterUser n'attribue un code qu'aux nouvelles inscriptions).
        if ($organization->referral_code === null) {
            $organization->update(['referral_code' => Str::lower(Str::random(8))]);
        }

        return Inertia::render('Settings/Referral', [
            'referralUrl' => url("/register?via={$organization->referral_code}"),
            'referredCount' => Organization::query()->where('referred_by_organization_id', $organization->id)->count(),
        ]);
    }
}
