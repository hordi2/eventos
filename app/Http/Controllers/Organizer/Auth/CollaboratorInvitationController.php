<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer\Auth;

use App\Domain\Organization\Actions\AcceptCollaboratorInvitation;
use App\Domain\Organization\Actions\RegisterInvitedCollaborator;
use App\Domain\Organization\Models\Collaborator;
use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Auth\RegisterInvitedCollaboratorRequest;
use App\Models\User;
use App\Support\Collaboration\DescribeCollaboratorEvents;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lien reçu par e-mail (Paramètres → Partage d'événements). L'URL porte le
 * slug de l'organisation pour poser le contexte multi-tenant avant de
 * chercher l'invitation (RLS), puis le jeton, seule preuve d'accès.
 */
final class CollaboratorInvitationController extends Controller
{
    public function show(Request $request, string $organization, string $token, DescribeCollaboratorEvents $describeCollaboratorEvents): Response
    {
        $collaborator = $this->findInvitation($organization, $token);
        $user = $request->user() instanceof User ? $request->user() : null;

        if ($user === null) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        $props = [
            'organizationName' => $collaborator->organization->name,
            'inviterName' => $collaborator->invitedBy?->name,
            'email' => $collaborator->email,
            'events' => $describeCollaboratorEvents->handle($collaborator),
            'expired' => $collaborator->isInvitationExpired(),
            'viewer' => $this->viewer($collaborator, $user),
            'acceptUrl' => route('collaborator-invitations.accept', ['organization' => $organization, 'token' => $token]),
            'registerUrl' => route('collaborator-invitations.register', ['organization' => $organization, 'token' => $token]),
        ];

        // Les props partagées (menu « Mes événements »...) sont calculées après
        // ce retour : l'organisation invitante ne doit pas s'y dévoiler à
        // quelqu'un qui n'en est pas encore membre.
        app(CurrentOrganization::class)->clear();

        return Inertia::render('Invitations/Show', $props);
    }

    public function accept(Request $request, string $organization, string $token, AcceptCollaboratorInvitation $acceptCollaboratorInvitation): RedirectResponse
    {
        $collaborator = $this->findInvitation($organization, $token);
        /** @var User $user */
        $user = $request->user();

        $this->ensureNotExpired($collaborator);
        abort_unless(Str::lower($user->email) === $collaborator->email, 403, 'Cette invitation est destinée à une autre adresse e-mail.');

        $acceptCollaboratorInvitation->handle($collaborator, $user);
        $request->session()->put('current_organization_id', $collaborator->organization_id);

        return redirect()->route('dashboard')->with('status', 'collaborator-invitation-accepted');
    }

    public function register(RegisterInvitedCollaboratorRequest $request, string $organization, string $token, RegisterInvitedCollaborator $registerInvitedCollaborator): RedirectResponse
    {
        $collaborator = $this->findInvitation($organization, $token);
        $this->ensureNotExpired($collaborator);

        if (User::query()->where('email', $collaborator->email)->exists()) {
            return back()->withErrors(['email' => "Un compte Itaza existe déjà pour cette adresse : connectez-vous pour accepter l'invitation."]);
        }

        $user = $registerInvitedCollaborator->handle($collaborator, $request->string('name')->toString(), $request->string('password')->toString());

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('current_organization_id', $collaborator->organization_id);

        return redirect()->route('dashboard')->with('status', 'collaborator-invitation-accepted');
    }

    private function findInvitation(string $organizationSlug, string $token): Collaborator
    {
        $organization = Organization::query()->where('slug', $organizationSlug)->firstOrFail();

        app(CurrentOrganization::class)->set($organization);

        // Une invitation acceptée n'a plus d'empreinte de jeton : son lien
        // tombe en 404 comme un jeton inventé.
        return Collaborator::query()
            ->with(['organization:id,name', 'invitedBy:id,name'])
            ->where('invitation_token_hash', Collaborator::hashToken($token))
            ->firstOrFail();
    }

    private function ensureNotExpired(Collaborator $collaborator): void
    {
        abort_if($collaborator->isInvitationExpired(), 410, "Cette invitation a expiré : demandez qu'on vous la renvoie.");
    }

    /**
     * Oriente la page : créer un compte, se connecter, accepter, ou changer
     * de compte quand la session ouverte n'est pas celle de l'invitation.
     */
    private function viewer(Collaborator $collaborator, ?User $user): string
    {
        if ($user !== null) {
            return Str::lower($user->email) === $collaborator->email ? 'recipient' : 'other-account';
        }

        return User::query()->where('email', $collaborator->email)->exists() ? 'existing-account' : 'new-account';
    }
}
