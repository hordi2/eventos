<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\Organization;
use App\Support\MultiTenancy\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Équivalent API de ResolveGuestEvent (voir son docblock) : l'organisation
 * d'un événement doit être connue AVANT toute requête soumise à la RLS
 * (section 4.1 du CLAUDE.md). La route ne porte qu'un id d'événement, sans
 * segment d'organisation : on résout donc les organisations candidates via
 * les adhésions (Membership) de l'utilisateur authentifié — cette table
 * n'est volontairement pas protégée par la RLS, exactement pour ce genre
 * de résolution du tenant (voir le docblock de Membership::class).
 *
 * Un événement introuvable dans aucune des organisations de l'utilisateur
 * renvoie 404, jamais 403 : ce choix évite de révéler à un utilisateur non
 * habilité qu'un identifiant d'événement existe ailleurs.
 */
final class ResolveApiCheckInEvent
{
    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $eventId = (int) $request->route('event');
        $user = $request->user();

        abort_if($user === null, 401);

        $organizationIds = Membership::query()->where('user_id', $user->id)->pluck('organization_id');

        foreach ($organizationIds as $organizationId) {
            $this->currentOrganization->set($organizationId);

            $event = Event::query()->find($eventId);

            if ($event !== null) {
                $organization = Organization::query()->findOrFail($organizationId);

                Gate::forUser($user)->authorize('checkIn', $organization);

                $request->attributes->set('checkInEvent', $event);

                return $next($request);
            }

            $this->currentOrganization->clear();
        }

        abort(404);
    }
}
