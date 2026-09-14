<?php

declare(strict_types=1);

namespace App\Support\Collaboration;

use App\Domain\Form\Models\Attendee;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\Registration;
use App\Domain\Messaging\Models\MessageAutomation;
use App\Domain\Organization\Models\CollaboratorPermission;
use App\Domain\Organization\Services\CollaboratorAccess;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Models\User;
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
    ) {}

    public function handle(Request $request, User $user, int $organizationId): void
    {
        $this->currentEvent->clear();

        if (! $this->collaboratorAccess->isCollaborator($user, $organizationId)) {
            return;
        }

        $route = $request->route();
        $routeName = $route instanceof Route ? (string) $route->getName() : '';
        $eventId = $route instanceof Route ? $this->eventIdFromRoute($route) : null;

        if ($eventId === null) {
            abort_unless($this->isAllowedWithoutEvent($routeName), 403, 'Cette page ne fait pas partie des événements partagés avec vous.');

            return;
        }

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

    /**
     * Les routes enfants (formulaire, billet, automatisation, participant) ne
     * portent pas {event} : l'événement est retrouvé depuis la ressource, sans
     * quoi un collaborateur pourrait viser celle d'un événement non partagé.
     */
    private function eventIdFromRoute(Route $route): ?int
    {
        $parameters = $route->parameters();

        if (isset($parameters['event'])) {
            return (int) $parameters['event'];
        }

        $eventId = match (true) {
            isset($parameters['form']) => Form::query()->whereKey((int) $parameters['form'])->value('event_id'),
            isset($parameters['ticketType']) => TicketType::query()->whereKey((int) $parameters['ticketType'])->value('event_id'),
            isset($parameters['priceTier']) => TicketType::query()
                ->whereKey(PriceTier::query()->whereKey((int) $parameters['priceTier'])->value('ticket_type_id'))
                ->value('event_id'),
            isset($parameters['messageAutomation']) => MessageAutomation::query()->whereKey((int) $parameters['messageAutomation'])->value('event_id'),
            isset($parameters['attendee']) => Registration::query()
                ->whereKey(Attendee::query()->whereKey((int) $parameters['attendee'])->value('registration_id'))
                ->value('event_id'),
            default => false,
        };

        if ($eventId === false) {
            return null;
        }

        abort_if($eventId === null, 404);

        return (int) $eventId;
    }
}
