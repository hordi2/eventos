<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer\Auth;

use App\Domain\Organization\Actions\AcceptTeamMemberInvitation;
use App\Domain\Organization\Actions\RegisterInvitedTeamMember;
use App\Domain\Organization\Models\MembershipInvitation;
use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Auth\RegisterInvitedTeamMemberRequest;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lien reçu par e-mail (Paramètres → Équipe). L'URL porte le slug de
 * l'organisation pour poser le contexte multi-tenant avant de chercher
 * l'invitation (RLS), puis le jeton, seule preuve d'accès — même mécanique
 * que CollaboratorInvitationController.
 */
final class TeamInvitationController extends Controller
{
    public function show(Request $request, string $organization, string $token): Response
    {
        $invitation = $this->findInvitation($organization, $token);
        $user = $request->user() instanceof User ? $request->user() : null;

        if ($user === null) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        $props = [
            'organizationName' => $invitation->organization->name,
            'inviterName' => $invitation->invitedBy?->name,
            'email' => $invitation->email,
            'roleLabel' => $invitation->role->label(),
            'expired' => $invitation->isInvitationExpired(),
            'viewer' => $this->viewer($invitation, $user),
            'acceptUrl' => route('team-invitations.accept', ['organization' => $organization, 'token' => $token]),
            'registerUrl' => route('team-invitations.register', ['organization' => $organization, 'token' => $token]),
        ];

        // Les props partagées (menu « Mes événements »...) sont calculées après
        // ce retour : l'organisation invitante ne doit pas s'y dévoiler à
        // quelqu'un qui n'en est pas encore membre.
        app(CurrentOrganization::class)->clear();

        return Inertia::render('Invitations/ShowTeamMember', $props);
    }

    public function accept(Request $request, string $organization, string $token, AcceptTeamMemberInvitation $acceptTeamMemberInvitation): RedirectResponse
    {
        $invitation = $this->findInvitation($organization, $token);
        /** @var User $user */
        $user = $request->user();

        $this->ensureNotExpired($invitation);
        abort_unless(Str::lower($user->email) === $invitation->email, 403, 'Cette invitation est destinée à une autre adresse e-mail.');

        $acceptTeamMemberInvitation->handle($invitation, $user);
        $request->session()->put('current_organization_id', $invitation->organization_id);

        return redirect()->route('dashboard')->with('status', 'team-member-invitation-accepted');
    }

    public function register(RegisterInvitedTeamMemberRequest $request, string $organization, string $token, RegisterInvitedTeamMember $registerInvitedTeamMember): RedirectResponse
    {
        $invitation = $this->findInvitation($organization, $token);
        $this->ensureNotExpired($invitation);

        if (User::query()->where('email', $invitation->email)->exists()) {
            return back()->withErrors(['email' => "Un compte Itaza existe déjà pour cette adresse : connectez-vous pour accepter l'invitation."]);
        }

        $user = $registerInvitedTeamMember->handle($invitation, $request->string('name')->toString(), $request->string('password')->toString());

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('current_organization_id', $invitation->organization_id);

        return redirect()->route('dashboard')->with('status', 'team-member-invitation-accepted');
    }

    private function findInvitation(string $organizationSlug, string $token): MembershipInvitation
    {
        $organization = Organization::query()->where('slug', $organizationSlug)->firstOrFail();

        app(CurrentOrganization::class)->set($organization);

        // Une invitation acceptée n'a plus d'empreinte de jeton : son lien
        // tombe en 404 comme un jeton inventé.
        return MembershipInvitation::query()
            ->with(['organization:id,name', 'invitedBy:id,name'])
            ->where('invitation_token_hash', MembershipInvitation::hashToken($token))
            ->firstOrFail();
    }

    private function ensureNotExpired(MembershipInvitation $invitation): void
    {
        abort_if($invitation->isInvitationExpired(), 410, "Cette invitation a expiré : demandez qu'on vous la renvoie.");
    }

    /**
     * Oriente la page : créer un compte, se connecter, accepter, ou changer
     * de compte quand la session ouverte n'est pas celle de l'invitation.
     */
    private function viewer(MembershipInvitation $invitation, ?User $user): string
    {
        if ($user !== null) {
            return Str::lower($user->email) === $invitation->email ? 'recipient' : 'other-account';
        }

        return User::query()->where('email', $invitation->email)->exists() ? 'existing-account' : 'new-account';
    }
}
