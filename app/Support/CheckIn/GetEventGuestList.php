<?php

declare(strict_types=1);

namespace App\Support\CheckIn;

use App\Domain\CheckIn\Data\GuestData;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Domain\Ticketing\Models\TicketStatus;
use Illuminate\Support\Facades\DB;

/**
 * Assemble la liste des invités attendus à un événement, RSVP (Domain/Form)
 * et billetterie payante (Domain/Ticketing) confondus. Vit hors de ces deux
 * modules pour la même raison que ComputeEventSegmentContacts (voir son
 * docblock) : ni l'un ni l'autre ne référence les modèles de l'autre
 * (section 3 du CLAUDE.md).
 *
 * Seuls les billets déjà émis (commande payée, M5.4) sont inclus : une
 * commande "paiement à l'arrivée" n'a pas encore de Ticket avant d'être
 * encaissée au poste de contrôle, ce cas relève de l'ajout d'un invité sur
 * place (T-063), pas du téléchargement de la liste (T-060).
 */
final class GetEventGuestList
{
    /**
     * @return list<GuestData>
     */
    public function handle(Event $event, ?string $search = null): array
    {
        return [
            ...$this->attendees($event, $search),
            ...$this->ticketHolders($event, $search),
        ];
    }

    /**
     * Relit un seul invité après un scan/enregistrement, pour renvoyer son
     * état à jour (nom, déjà enregistré ou non) sans reconstruire toute la
     * liste.
     */
    public function findOne(Event $event, string $guestType, int $id): ?GuestData
    {
        $matches = $guestType === 'attendee'
            ? $this->attendees($event, null, $id)
            : $this->ticketHolders($event, null, $id);

        return $matches[0] ?? null;
    }

    /**
     * @return list<GuestData>
     */
    private function attendees(Event $event, ?string $search, ?int $onlyId = null): array
    {
        $query = DB::table('attendees')
            ->join('registrations', 'registrations.id', '=', 'attendees.registration_id')
            ->leftJoin('check_ins', function ($join): void {
                $join->on('check_ins.attendee_id', '=', 'attendees.id')
                    ->where('check_ins.direction', 'check_in')
                    ->where('check_ins.status', 'accepted');
            })
            ->where('registrations.organization_id', $event->organization_id)
            ->where('registrations.event_id', $event->id)
            ->where('registrations.status', RegistrationStatus::Confirmed->value)
            ->where('attendees.is_primary', true)
            ->whereNull('attendees.deleted_at')
            ->whereNull('registrations.deleted_at');

        if ($onlyId !== null) {
            $query->where('attendees.id', $onlyId);
        }

        if ($search !== null && $search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('attendees.first_name', 'ilike', "%{$search}%")
                    ->orWhere('attendees.last_name', 'ilike', "%{$search}%")
                    ->orWhere('attendees.email', 'ilike', "%{$search}%")
                    ->orWhere('registrations.phone_e164', 'ilike', "%{$search}%");
            });
        }

        return $query
            ->get([
                'attendees.id',
                'attendees.first_name',
                'attendees.last_name',
                'attendees.email',
                'registrations.phone_e164',
                'check_ins.recorded_at as checked_in_at',
            ])
            ->map(fn (object $row): GuestData => new GuestData(
                guestType: 'attendee',
                id: (int) $row->id,
                name: trim("{$row->first_name} {$row->last_name}"),
                email: $row->email,
                phone: $row->phone_e164,
                checkedIn: $row->checked_in_at !== null,
                checkedInAt: $row->checked_in_at,
            ))
            ->all();
    }

    /**
     * @return list<GuestData>
     */
    private function ticketHolders(Event $event, ?string $search, ?int $onlyId = null): array
    {
        $query = DB::table('tickets')
            ->join('order_items', 'order_items.id', '=', 'tickets.order_item_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('check_ins', function ($join): void {
                $join->on('check_ins.ticket_id', '=', 'tickets.id')
                    ->where('check_ins.direction', 'check_in')
                    ->where('check_ins.status', 'accepted');
            })
            ->where('orders.organization_id', $event->organization_id)
            ->where('orders.event_id', $event->id)
            ->where('orders.status', OrderStatus::Paid->value)
            ->where('tickets.status', TicketStatus::Valid->value)
            ->whereNull('tickets.deleted_at')
            ->whereNull('orders.deleted_at');

        if ($onlyId !== null) {
            $query->where('tickets.id', $onlyId);
        }

        if ($search !== null && $search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('orders.buyer_name', 'ilike', "%{$search}%")
                    ->orWhere('orders.buyer_email', 'ilike', "%{$search}%")
                    ->orWhere('orders.buyer_phone_e164', 'ilike', "%{$search}%")
                    ->orWhere('tickets.id', '=', is_numeric($search) ? (int) $search : -1);
            });
        }

        return $query
            ->get([
                'tickets.id',
                'orders.buyer_name',
                'orders.buyer_email',
                'orders.buyer_phone_e164',
                'check_ins.recorded_at as checked_in_at',
            ])
            ->map(fn (object $row): GuestData => new GuestData(
                guestType: 'ticket',
                id: (int) $row->id,
                name: $row->buyer_name,
                email: $row->buyer_email,
                phone: $row->buyer_phone_e164,
                checkedIn: $row->checked_in_at !== null,
                checkedInAt: $row->checked_in_at,
            ))
            ->all();
    }
}
