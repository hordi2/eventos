<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Support\MultiTenancy\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve une route au propriétaire de l'organisation courante (M0.5 :
 * le journal d'audit n'est consultable et exportable que par le owner).
 * Vérification directe sur Membership plutôt qu'un système de policies
 * complet — la matrice de rôles détaillée (T-004) n'existe pas encore.
 */
final class EnsureUserIsOrganizationOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $organizationId = app(CurrentOrganization::class)->id();

        $isOwner = $user !== null
            && $organizationId !== null
            && Membership::query()
                ->where('user_id', $user->id)
                ->where('organization_id', $organizationId)
                ->where('role', MembershipRole::Owner)
                ->exists();

        abort_unless($isOwner, 403);

        return $next($request);
    }
}
