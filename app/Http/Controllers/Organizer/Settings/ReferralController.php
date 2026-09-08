<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer\Settings;

use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Settings\SendReferralInvitationRequest;
use App\Mail\ReferralInvitationMail;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class ReferralController extends Controller
{
    public function edit(): Response
    {
        $organization = $this->currentOrganization();

        return Inertia::render('Settings/Referral', [
            'referralUrl' => $this->referralUrl($organization),
            'referredCount' => Organization::query()->where('referred_by_organization_id', $organization->id)->count(),
            'rewardedCount' => Organization::query()
                ->where('referred_by_organization_id', $organization->id)
                ->whereNotNull('referral_rewarded_at')
                ->count(),
        ]);
    }

    public function invite(SendReferralInvitationRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        Mail::to($request->string('email')->toString())
            ->queue(new ReferralInvitationMail($user->name, $this->referralUrl($this->currentOrganization())));

        return back()->with('status', 'referral-invitation-sent');
    }

    /**
     * Rattrapage pour les organisations créées avant ce chantier
     * (RegisterUser n'attribue un code qu'aux nouvelles inscriptions).
     */
    private function referralUrl(Organization $organization): string
    {
        if ($organization->referral_code === null) {
            $organization->update(['referral_code' => Str::lower(Str::random(8))]);
        }

        return url("/register?via={$organization->referral_code}");
    }

    private function currentOrganization(): Organization
    {
        return Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId());
    }
}
