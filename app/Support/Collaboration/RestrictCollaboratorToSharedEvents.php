<?php

declare(strict_types=1);

namespace App\Support\Collaboration;

use App\Domain\Organization\Models\CollaboratorPermission;
use App\Domain\Organization\Services\CollaboratorAccess;
use App\Models\User;
use App\Support\Events\ResolveRouteEventId;
use App\Support\MultiTenancy\CurrentEvent;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

/**
 * Refus par défaut pour un collaborateur, appelé par ResolveCurrentOrganization
 * sur chaque page organisateur : beaucoup de lectures cloisonnées (contacts,
 * modèles, tableau de bord...) ne passent par aucune policy, le scope
 * organisation suffisant pour les membres. Un collaborateur appartient à
 * l'organisation sans avoir le droit de tout y lire : il n'atteint que les
 * pages de ses événements partagés et quelques pages de son propre compte.
 *
 * Sur une page d'événement, l'événement est posé dans CurrentEvent :
 * OrganizationPolicy borne alors ses capacités à sa permission sur cet
 * événement, via les contrôles d'accès déjà en place.
 */
final class RestrictCollaboratorToSharedEvents
{
    /**
     * @var list<string>
     */
    private const ROUTES_WITHOUT_EVENT = [
        'dashboard',
        'help.index',
        'settings.profile.edit',
        'settings.profile.update',
        'settings.profile.destroy',
        'settings.security.edit',
        'settings.security.password',
        'settings.security.mfa',
    ];

    private const ROUTE_PREFIX_WITHOUT_EVENT = 'community.';

    /**
     * Portent sur un événement partagé mais créent autre chose que lui : la
     * duplication ajouterait un nouvel événement à l'organisation.
     *
     * @var list<string>
     */
    private const FORBIDDEN_EVENT_ROUTES = ['events.duplicate'];

    public function __construct(
        private readonly CollaboratorAccess $collaboratorAccess,
        private readonly CurrentEvent $currentEvent,
        private readonly ResolveRouteEventId $resolveRouteEventId,
    ) {}

    public function handle(Request $request, User $user, int $organizationId): void
    {
        $this->currentEvent->clear();

        if (! $this->collaboratorAccess->isCollaborator($user, $organizationId)) {
            return;
        }

        $route = $request->route();
        $routeName = $route instanceof Route ? (string) $route->getName() : '';

        if (! $route instanceof Route || ! $this->resolveRouteEventId->targetsEvent($route)) {
            abort_unless($this->isAllowedWithoutEvent($routeName), 403, 'Cette page ne fait pas partie des événements partagés avec vous.');

            return;
        }

        // Ressource enfant introuvable : sans cela, un collaborateur pourrait
        // viser celle d'un événement non partagé.
        $eventId = $this->resolveRouteEventId->handle($route);
        abort_if($eventId === null, 404);

        abort_if(in_array($routeName, self::FORBIDDEN_EVENT_ROUTES, true), 403);

        // 404 plutôt que 403 : ne pas confirmer qu'un événement non partagé existe.
        abort_if($this->collaboratorAccess->permissionFor($user, $organizationId, $eventId) === CollaboratorPermission::None, 404);

        $this->currentEvent->set($eventId);
    }

    private function isAllowedWithoutEvent(string $routeName): bool
    {
        return in_array($routeName, self::ROUTES_WITHOUT_EVENT, true)
            || str_starts_with($routeName, self::ROUTE_PREFIX_WITHOUT_EVENT);
    }
}
