<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Models\User;
use App\Support\Collaboration\RestrictCollaboratorToSharedEvents;
use App\Support\MultiTenancy\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveCurrentOrganization
{
    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
        private readonly RestrictCollaboratorToSharedEvents $restrictCollaboratorToSharedEvents,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $organizationId = $this->resolveOrganizationId($request);

        // Sans organisation, la moindre lecture cloisonnée plus loin lèverait
        // MissingOrganizationContextException en pleine page (erreur 500
        // brute). Le cas est anormal — l'inscription crée toujours une
        // organisation, l'acceptation d'une invitation une adhésion de
        // collaborateur — mais reste atteignable si la dernière adhésion de
        // l'utilisateur a été retirée entre-temps.
        abort_if($organizationId === null, 403, "Votre compte n'appartient à aucune organisation.");

        $this->currentOrganization->set($organizationId);

        // Ici plutôt que dans un middleware à ajouter route par route : aucune
        // page organisateur ne peut l'oublier.
        $this->restrictCollaboratorToSharedEvents->handle($request, $user, $organizationId);

        return $next($request);
    }

    /**
     * L'adhésion est revérifiée à chaque requête, jamais déduite de la seule
     * session : un membre retiré de l'organisation garderait sinon l'accès en
     * lecture à ses données jusqu'à l'expiration de sa session, puisque les
     * lectures cloisonnées ordinaires (tableau de bord, contacts...) ne
     * passent par aucune policy — seul le scope organisation les filtre
     * (règle 4.1 du CLAUDE.md).
     */
    private function resolveOrganizationId(Request $request): ?int
    {
        $sessionOrganizationId = $request->session()->get('current_organization_id');

        if ($sessionOrganizationId !== null) {
            $confirmed = Membership::query()
                ->where('user_id', $request->user()?->id)
                ->where('organization_id', $sessionOrganizationId)
                ->value('organization_id');

            if ($confirmed !== null) {
                return (int) $confirmed;
            }

            // L'organisation choisie n'est plus accessible : on repart sur la
            // première adhésion restante plutôt que de laisser une session
            // pointer indéfiniment vers une organisation quittée.
            $request->session()->forget('current_organization_id');
        }

        // Son propre espace avant ceux où l'on n'est que collaborateur : un
        // organisateur invité ailleurs retrouve ses événements à la connexion
        // et passe à l'autre espace par le sélecteur du menu utilisateur.
        $organizationId = Membership::query()
            ->where('user_id', $request->user()?->id)
            ->orderByRaw('role = ?', [MembershipRole::Collaborator->value])
            ->orderBy('id')
            ->value('organization_id');

        return $organizationId !== null ? (int) $organizationId : null;
    }
}
