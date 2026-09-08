<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer\Settings;

use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Settings\UpdatePasswordRequest;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class SecurityController extends Controller
{
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $organization = $this->currentOrganization();

        return Inertia::render('Settings/Security', [
            // (bool) défensif : create() ne relit pas les valeurs par défaut
            // de la base pour les colonnes non précisées (même piège que
            // OrganizationPolicy::check() pour allow_editor_financial_access).
            'mfaEmailEnabled' => (bool) $user->mfa_email_enabled,
            'organizationMfa' => $organization !== null && Gate::forUser($user)->allows('manageSecurity', $organization) ? [
                'requireMfaForMembers' => (bool) $organization->require_mfa_for_members,
            ] : null,
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'password' => $request->string('password')->toString(),
        ])->save();

        return back()->with('status', 'password-updated');
    }

    public function updateMfa(Request $request): RedirectResponse
    {
        $request->validate(['mfa_email_enabled' => ['required', 'boolean']]);

        /** @var User $user */
        $user = $request->user();
        $user->update(['mfa_email_enabled' => $request->boolean('mfa_email_enabled')]);

        return back()->with('status', 'mfa-updated');
    }

    public function updateOrganizationMfa(Request $request): RedirectResponse
    {
        $organization = $this->currentOrganization();

        if ($organization === null) {
            abort(404);
        }

        Gate::forUser($request->user())->authorize('manageSecurity', $organization);

        $request->validate(['require_mfa_for_members' => ['required', 'boolean']]);

        $organization->update(['require_mfa_for_members' => $request->boolean('require_mfa_for_members')]);

        return back()->with('status', 'organization-mfa-updated');
    }

    private function currentOrganization(): ?Organization
    {
        $organizationId = app(CurrentOrganization::class)->id();

        return $organizationId !== null ? Organization::query()->find($organizationId) : null;
    }
}
