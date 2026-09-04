<?php

declare(strict_types=1);

namespace App\Support\Export;

use App\Domain\Analytics\Models\ExportType;
use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Support\CheckIn\GetEventGuestList;
use App\Support\Segments\ComputeEventSegmentContacts;
use App\Support\Segments\EventSegment;
use Generator;
use Illuminate\Support\Facades\DB;

/**
 * Traverse Contact (Domain/Contact), Form (Domain/Form) et Ticketing
 * (Domain/Ticketing) : ne peut pas vivre dans Domain/Analytics (section 3
 * du CLAUDE.md), même raisonnement que GetEventGuestList et
 * ComputeEventSegmentContacts, réutilisées ici plutôt que dupliquées.
 *
 * Le filtre par segment (AC de T-071) ne s'applique qu'au type "contacts" :
 * c'est le seul des quatre où « segment RSVP » a un sens direct — les
 * inscriptions/commandes/check-ins ont déjà leur propre statut en colonne.
 * Un segment fourni pour un autre type est simplement ignoré.
 */
final class BuildExportRows
{
    public function __construct(
        private readonly ComputeEventSegmentContacts $computeEventSegmentContacts,
        private readonly GetEventGuestList $getEventGuestList,
    ) {}

    /**
     * @return array<string, string>
     */
    public function columns(ExportType $type): array
    {
        return match ($type) {
            ExportType::Contacts => [
                'first_name' => 'Prénom',
                'last_name' => 'Nom',
                'email' => 'E-mail',
                'phone' => 'Téléphone',
                'status' => 'Statut',
            ],
            ExportType::Registrations => [
                'first_name' => 'Prénom',
                'last_name' => 'Nom',
                'email' => 'E-mail',
                'phone' => 'Téléphone',
                'status' => 'Statut',
                'submitted_at' => 'Date de soumission',
            ],
            ExportType::Orders => [
                'buyer_name' => 'Acheteur',
                'buyer_email' => 'E-mail',
                'buyer_phone' => 'Téléphone',
                'status' => 'Statut',
                'total' => 'Montant',
                'paid_at' => 'Date de paiement',
            ],
            ExportType::CheckIns => [
                'name' => 'Nom',
                'email' => 'E-mail',
                'phone' => 'Téléphone',
                'checked_in_at' => "Heure d'arrivée",
            ],
        };
    }

    /**
     * @param  list<string>  $columnKeys
     * @return Generator<int, array<string, string>>
     */
    public function rows(Event $event, ExportType $type, array $columnKeys, ?EventSegment $segment): Generator
    {
        return match ($type) {
            ExportType::Contacts => $this->contactsRows($event, $columnKeys, $segment),
            ExportType::Registrations => $this->registrationsRows($event, $columnKeys),
            ExportType::Orders => $this->ordersRows($event, $columnKeys),
            ExportType::CheckIns => $this->checkInsRows($event, $columnKeys),
        };
    }

    /**
     * @param  list<string>  $columnKeys
     * @return Generator<int, array<string, string>>
     */
    private function contactsRows(Event $event, array $columnKeys, ?EventSegment $segment): Generator
    {
        $contacts = $segment !== null
            ? $this->computeEventSegmentContacts->query($event, $segment)->cursor()
            : Contact::query()
                ->where('organization_id', $event->organization_id)
                ->whereIn('id', function ($query) use ($event): void {
                    $query->select('contact_id')
                        ->from('registrations')
                        ->where('event_id', $event->id)
                        ->whereNotNull('contact_id')
                        ->whereNull('deleted_at');
                })
                ->cursor();

        $statuses = DB::table('registrations')
            ->where('organization_id', $event->organization_id)
            ->where('event_id', $event->id)
            ->whereNull('deleted_at')
            ->pluck('status', 'contact_id');

        foreach ($contacts as $contact) {
            yield $this->pick([
                'first_name' => (string) $contact->first_name,
                'last_name' => (string) $contact->last_name,
                'email' => (string) $contact->email,
                'phone' => (string) $contact->phone_e164,
                'status' => $this->registrationStatusLabel($statuses->get($contact->id)),
            ], $columnKeys);
        }
    }

    /**
     * @param  list<string>  $columnKeys
     * @return Generator<int, array<string, string>>
     */
    private function registrationsRows(Event $event, array $columnKeys): Generator
    {
        $registrations = Registration::query()
            ->where('organization_id', $event->organization_id)
            ->where('event_id', $event->id)
            ->cursor();

        foreach ($registrations as $registration) {
            yield $this->pick([
                'first_name' => (string) $registration->first_name,
                'last_name' => (string) $registration->last_name,
                'email' => (string) $registration->email,
                'phone' => (string) $registration->phone_e164,
                'status' => $this->registrationStatusLabel($registration->status->value),
                'submitted_at' => $registration->created_at?->toDateTimeString() ?? '',
            ], $columnKeys);
        }
    }

    /**
     * @param  list<string>  $columnKeys
     * @return Generator<int, array<string, string>>
     */
    private function ordersRows(Event $event, array $columnKeys): Generator
    {
        $orders = Order::query()
            ->where('organization_id', $event->organization_id)
            ->where('event_id', $event->id)
            ->cursor();

        foreach ($orders as $order) {
            yield $this->pick([
                'buyer_name' => (string) $order->buyer_name,
                'buyer_email' => (string) $order->buyer_email,
                'buyer_phone' => (string) $order->buyer_phone_e164,
                'status' => $this->orderStatusLabel($order->status),
                'total' => $order->total->format(),
                'paid_at' => $order->paid_at?->toDateTimeString() ?? '',
            ], $columnKeys);
        }
    }

    /**
     * @param  list<string>  $columnKeys
     * @return Generator<int, array<string, string>>
     */
    private function checkInsRows(Event $event, array $columnKeys): Generator
    {
        foreach ($this->getEventGuestList->handle($event) as $guest) {
            if (! $guest->checkedIn) {
                continue;
            }

            yield $this->pick([
                'name' => $guest->name,
                'email' => (string) ($guest->email ?? ''),
                'phone' => (string) ($guest->phone ?? ''),
                'checked_in_at' => (string) ($guest->checkedInAt ?? ''),
            ], $columnKeys);
        }
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $columnKeys
     * @return array<string, string>
     */
    private function pick(array $row, array $columnKeys): array
    {
        $result = [];

        foreach ($columnKeys as $key) {
            $result[$key] = $row[$key] ?? '';
        }

        return $result;
    }

    private function registrationStatusLabel(?string $status): string
    {
        return match ($status) {
            'confirmed' => 'Confirmé',
            'waitlisted' => "Liste d'attente",
            'cancelled' => 'Décliné',
            default => 'Sans réponse',
        };
    }

    private function orderStatusLabel(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Pending => 'En attente',
            OrderStatus::PaymentOnSite => "Paiement à l'arrivée",
            OrderStatus::Paid => 'Payée',
            OrderStatus::Failed => 'Échouée',
            OrderStatus::Expired => 'Expirée',
            OrderStatus::Refunded => 'Remboursée',
        };
    }
}
