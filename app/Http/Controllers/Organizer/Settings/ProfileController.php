<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer\Settings;

use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Settings\DeleteAccountRequest;
use App\Http\Requests\Organizer\Settings\UpdateProfileRequest;
use App\Models\User;
use App\Support\Billing\GetOrganizationUsage;
use App\Support\Gdpr\AnonymizeUser;
use App\Support\Gdpr\SoleOrganizationOwnerException;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class ProfileController extends Controller
{
    public function edit(Request $request, AnonymizeUser $anonymizeUser, GetOrganizationUsage $getOrganizationUsage): Response
    {
        /** @var User $user */
        $user = $request->user();
        $organizationId = app(CurrentOrganization::class)->id();
        $organization = $organizationId !== null ? Organization::query()->find($organizationId) : null;
        $usage = $organization !== null ? $getOrganizationUsage->handle($organization) : null;

        return Inertia::render('Settings/Profile', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => $user->hasVerifiedEmail(),
            ],
            'isSoleOrganizationOwner' => $anonymizeUser->isSoleOwnerOfAnOrganization($user),
            'plan' => $organization !== null && $usage !== null ? [
                'label' => $organization->plan->label(),
                'usage' => [
                    'registrations' => ['used' => $usage->registrationsThisMonth, 'quota' => $usage->registrationsQuota],
                    'emails' => ['used' => $usage->emailsThisMonth, 'quota' => $usage->emailsQuota],
                    'active_events' => ['used' => $usage->activeEvents, 'quota' => $usage->activeEventsQuota],
                ],
            ] : null,
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $emailChanged = $request->string('email')->toString() !== $user->email;

        $user->fill([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
        ]);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('status', 'profile-updated');
    }

    public function destroy(DeleteAccountRequest $request, AnonymizeUser $anonymizeUser): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $anonymizeUser->handle($user);
        } catch (SoleOrganizationOwnerException $exception) {
            return back()->withErrors(['password' => $exception->getMessage()]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'account-deleted');
    }
}
