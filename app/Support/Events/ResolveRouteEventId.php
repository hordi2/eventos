<?php

declare(strict_types=1);

namespace App\Support\Events;

use App\Domain\Form\Models\Attendee;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\Registration;
use App\Domain\Messaging\Models\MessageAutomation;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use Illuminate\Routing\Route;

/**
 * Événement sur lequel porte une route organisateur. Les routes enfants
 * (formulaire, type de billet, tarif, automatisation, participant) ne
 * portent pas {event} : l'événement est retrouvé depuis la ressource.
 *
 * Traverse plusieurs modules du domaine, d'où sa place dans Support.
 * Les requêtes passent par le scope organisation courant.
 */
final class ResolveRouteEventId
{
    private const CHILD_PARAMETERS = ['form', 'ticketType', 'priceTier', 'messageAutomation', 'attendee'];

    public function targetsEvent(Route $route): bool
    {
        $parameters = $route->parameters();

        foreach (['event', ...self::CHILD_PARAMETERS] as $name) {
            if (isset($parameters[$name])) {
                return true;
            }
        }

        return false;
    }

    /**
     * null quand la route ne porte sur aucun événement ou que la ressource
     * enfant est introuvable dans l'organisation courante.
     */
    public function handle(Route $route): ?int
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
            default => null,
        };

        return $eventId !== null ? (int) $eventId : null;
    }
}
