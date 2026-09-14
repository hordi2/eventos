<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Support\MultiTenancy\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contexte multi-tenant des points d'entrée API qui ne portent aucun
 * identifiant d'événement (abonnements REST Hooks de Zapier/n8n) : contrairement
 * à ResolveApiCheckInEvent, il n'y a rien dans l'URL pour départager les
 * organisations, on prend donc la première adhésion de l'utilisateur —
 * même convention que ResolveCurrentOrganization côté web.
 *
 * Limite connue et documentée dans le guide d'intégration : une clé API
 * d'un utilisateur membre de plusieurs organisations ne pilote que la
 * première. Une clé par organisation reste la marche à suivre.
 */
final class ResolveApiOrganization
{
    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null, 401);

        // Son propre espace d'abord : un espace où l'on n'est que
        // collaborateur n'accorde jamais manageIntegrations.
        $organizationId = Membership::query()
            ->where('user_id', $user->id)
            ->orderByRaw('role = ?', [MembershipRole::Collaborator->value])
            ->orderBy('id')
            ->value('organization_id');

        abort_if($organizationId === null, 403, "Votre compte n'appartient à aucune organisation.");

        $this->currentOrganization->set((int) $organizationId);

        $organization = Organization::query()->findOrFail($organizationId);

        Gate::forUser($user)->authorize('manageIntegrations', $organization);

        $request->attributes->set('apiOrganization', $organization);

        return $next($request);
    }
}
