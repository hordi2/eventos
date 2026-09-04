<?php

declare(strict_types=1);

namespace App\Support\Dashboard;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Support\Segments\ComputeEventSegmentContacts;
use App\Support\Segments\EventSegment;
use Illuminate\Support\Facades\DB;

/**
 * Traverse Domain/Form, Domain/Ticketing et Domain/CheckIn : vit hors de ces
 * modules pour la même raison que GetEventGuestList (voir son docblock).
 *
 * Seuls les blocs « inscriptions » (répartition des réponses, courbe
 * cumulée) et « jour J » (présents, taux de présence, courbe d'arrivée) de
 * M8.1 sont couverts : les blocs financier, communication et logistique du
 * cahier des charges (§M8.1) restent hors périmètre.
 *
 * La répartition confirmé/décliné/sans réponse/liste d'attente ne concerne
 * que le parcours RSVP (Domain/Form) : un billet payé n'a pas de notion de
 * « décliné » ou « sans réponse », on l'a acheté ou pas. « Invitations
 * envoyées » n'est délibérément pas repris ici : aucune donnée fiable ne
 * relie un envoi (Domain/Messaging, EmailMessage/WhatsappMessage) à un
 * événement précis aujourd'hui — signalé plutôt que reconstruit à la volée
 * avec un nombre qui ne serait pas fiable.
 *
 * Uniquement des requêtes d'agrégation (COUNT/GROUP BY), jamais de
 * récupération ligne à ligne : condition du critère d'acceptation
 * « chargement en < 1 s sur un événement de 5 000 inscrits ».
 */
final class GetEventDashboardStats
{
    public function __construct(
        private readonly ComputeEventSegmentContacts $computeEventSegmentContacts,
    ) {}

    public function handle(Event $event): DashboardStatsData
    {
        $confirmedCount = $this->confirmedGuestCount($event);
        $presentCount = $this->presentGuestCount($event);

        return new DashboardStatsData(
            confirmedCount: $confirmedCount,
            presentCount: $presentCount,
            presenceRate: $confirmedCount > 0 ? $presentCount / $confirmedCount : 0.0,
            registrationCurve: $this->registrationCurve($event),
            arrivalCurve: $this->arrivalCurve($event),
            rsvpConfirmedCount: $this->computeEventSegmentContacts->query($event, EventSegment::Confirmes)->count(),
            rsvpDeclinedCount: $this->computeEventSegmentContacts->query($event, EventSegment::Declines)->count(),
            rsvpNoResponseCount: $this->computeEventSegmentContacts->query($event, EventSegment::SansReponse)->count(),
            rsvpWaitlistedCount: $this->waitlistedCount($event),
        );
    }

    private function waitlistedCount(Event $event): int
    {
        return DB::table('registrations')
            ->where('organization_id', $event->organization_id)
            ->where('event_id', $event->id)
            ->where('status', RegistrationStatus::Waitlisted->value)
            ->whereNull('deleted_at')
            ->count();
    }

    private function confirmedGuestCount(Event $event): int
    {
        $registrations = DB::table('registrations')
            ->join('attendees', 'attendees.registration_id', '=', 'registrations.id')
            ->where('registrations.organization_id', $event->organization_id)
            ->where('registrations.event_id', $event->id)
            ->where('registrations.status', RegistrationStatus::Confirmed->value)
            ->where('attendees.is_primary', true)
            ->whereNull('attendees.deleted_at')
            ->whereNull('registrations.deleted_at')
            ->count();

        $tickets = DB::table('tickets')
            ->join('order_items', 'order_items.id', '=', 'tickets.order_item_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.organization_id', $event->organization_id)
            ->where('orders.event_id', $event->id)
            ->where('orders.status', OrderStatus::Paid->value)
            ->where('tickets.status', 'valid')
            ->whereNull('tickets.deleted_at')
            ->whereNull('orders.deleted_at')
            ->count();

        return $registrations + $tickets;
    }

    private function presentGuestCount(Event $event): int
    {
        return DB::table('check_ins')
            ->where('organization_id', $event->organization_id)
            ->where('event_id', $event->id)
            ->where('direction', 'check_in')
            ->where('status', 'accepted')
            ->count();
    }

    /**
     * @return list<array{date: string, cumulative: int}>
     */
    private function registrationCurve(Event $event): array
    {
        $registrationsByDay = DB::table('registrations')
            ->join('attendees', 'attendees.registration_id', '=', 'registrations.id')
            ->where('registrations.organization_id', $event->organization_id)
            ->where('registrations.event_id', $event->id)
            ->where('registrations.status', RegistrationStatus::Confirmed->value)
            ->where('attendees.is_primary', true)
            ->whereNull('attendees.deleted_at')
            ->whereNull('registrations.deleted_at')
            ->selectRaw('date(timezone(?, registrations.registered_at)) as day, count(*) as c', [$event->timezone])
            ->groupBy('day')
            ->pluck('c', 'day');

        $ticketsByDay = DB::table('orders')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->join('tickets', 'tickets.order_item_id', '=', 'order_items.id')
            ->where('orders.organization_id', $event->organization_id)
            ->where('orders.event_id', $event->id)
            ->where('orders.status', OrderStatus::Paid->value)
            ->where('tickets.status', 'valid')
            ->whereNull('tickets.deleted_at')
            ->whereNull('orders.deleted_at')
            ->selectRaw('date(timezone(?, orders.paid_at)) as day, count(*) as c', [$event->timezone])
            ->groupBy('day')
            ->pluck('c', 'day');

        $days = collect($registrationsByDay->keys())->merge($ticketsByDay->keys())->unique()->sort()->values();

        $cumulative = 0;

        return $days
            ->map(function (string $day) use ($registrationsByDay, $ticketsByDay, &$cumulative): array {
                $cumulative += (int) ($registrationsByDay[$day] ?? 0) + (int) ($ticketsByDay[$day] ?? 0);

                return ['date' => $day, 'cumulative' => $cumulative];
            })
            ->all();
    }

    /**
     * @return list<array{hour: string, count: int}>
     */
    private function arrivalCurve(Event $event): array
    {
        return DB::table('check_ins')
            ->where('organization_id', $event->organization_id)
            ->where('event_id', $event->id)
            ->where('direction', 'check_in')
            ->where('status', 'accepted')
            ->selectRaw("to_char(timezone(?, recorded_at), 'YYYY-MM-DD HH24:00') as hour, count(*) as c", [$event->timezone])
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->map(fn (object $row): array => ['hour' => $row->hour, 'count' => (int) $row->c])
            ->all();
    }
}
