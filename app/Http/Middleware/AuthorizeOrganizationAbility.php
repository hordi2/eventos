<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Organization\Models\Organization;
use App\Support\MultiTenancy\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vérifie une capacité de la matrice de rôles (OrganizationPolicy) sur
 * l'organisation courante. Gate::authorize() lève une AuthorizationException,
 * rendue en 403 par défaut — jamais un 404 ni une erreur serveur.
 */
final class AuthorizeOrganizationAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $organizationId = app(CurrentOrganization::class)->id();

        abort_if($organizationId === null, 403);

        $organization = Organization::query()->findOrFail($organizationId);

        Gate::authorize($ability, $organization);

        return $next($request);
    }
}
