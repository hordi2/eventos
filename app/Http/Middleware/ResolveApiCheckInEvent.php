<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\CollaboratorPermission;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Services\CollaboratorAccess;
use App\Models\User;
use App\Support\MultiTenancy\CurrentEvent;
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
 * habilité qu'un identifiant d'événement existe ailleurs. Même règle pour un
 * collaborateur et un événement qui ne lui est pas partagé.
 */
final class ResolveApiCheckInEvent
{
    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
        private readonly CurrentEvent $currentEvent,
        private readonly CollaboratorAccess $collaboratorAccess,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $eventId = (int) $request->route('event');
        $user = $request->user();

        abort_if(! $user instanceof User, 401);

        $this->currentEvent->clear();

        $memberships = Membership::query()->where('user_id', $user->id)->get(['organization_id', 'role']);

        foreach ($memberships as $membership) {
            $this->currentOrganization->set($membership->organization_id);

            $event = Event::query()->find($eventId);

            if ($event !== null && $this->reachesEvent($user, $membership, $event)) {
                $organization = Organization::query()->findOrFail($membership->organization_id);

                Gate::forUser($user)->authorize('checkIn', $organization);

                $request->attributes->set('checkInEvent', $event);

                return $next($request);
            }

            $this->currentOrganization->clear();
        }

        abort(404);
    }

    /**
     * Pose aussi CurrentEvent pour un collaborateur : OrganizationPolicy borne
     * ses capacités à la permission qu'il a sur cet événement.
     */
    private function reachesEvent(User $user, Membership $membership, Event $event): bool
    {
        if ($membership->role !== MembershipRole::Collaborator) {
            return true;
        }

        if ($this->collaboratorAccess->permissionFor($user, $membership->organization_id, $event->id) === CollaboratorPermission::None) {
            return false;
        }

        $this->currentEvent->set($event->id);

        return true;
    }
}
