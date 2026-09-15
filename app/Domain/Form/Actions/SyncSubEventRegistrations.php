<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Data\SubEventContext;
use App\Domain\Form\Models\Attendee;
use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Form\SubEventFullException;
use App\Support\Capacity\Actions\ReleaseCapacity;
use App\Support\Capacity\Actions\ReserveCapacity;
use App\Support\Capacity\Data\ReservationOutcome;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Inscriptions aux événements secondaires (T-013) : une inscription
 * rattachée (parent_registration_id) par session cochée, portée par
 * l'événement secondaire lui-même. Elle tient sa propre capacité — une
 * place par personne du groupe —, sa liste d'attente et son check-in, et
 * reprend les personnes de l'inscription principale, dans le même ordre.
 *
 * Ne référence jamais Domain/Event : les sessions arrivent déjà résolues en
 * SubEventContext (section 3 du CLAUDE.md).
 */
final class SyncSubEventRegistrations
{
    public function __construct(
        private readonly ReserveCapacity $reserveCapacity,
        private readonly ReleaseCapacity $releaseCapacity,
    ) {}

    /**
     * Identifiants des sessions proposées par un bloc (config.sub_events).
     *
     * @param  array<string, mixed>  $config
     * @return list<int>
     */
    public static function offeredIds(array $config): array
    {
        return array_values(array_map(
            fn (mixed $subEvent): int => is_array($subEvent) ? (int) ($subEvent['id'] ?? 0) : 0,
            (array) ($config['sub_events'] ?? []),
        ));
    }

    /**
     * Sessions cochées dans les blocs « Événements secondaires » visibles,
     * limitées à celles que le bloc propose et qui existent encore.
     *
     * @param  array<string, mixed>  $answers
     * @param  array<string, array{visible: bool, required: bool}>  $visibility
     * @param  list<SubEventContext>  $subEvents
     * @return list<SubEventContext>
     */
    public function selected(FormVersion $version, array $answers, array $visibility, array $subEvents): array
    {
        $selectedIds = [];

        foreach ($version->fields as $field) {
            if ($field->type !== FieldType::SubEvents || ! ($visibility[$field->key]['visible'] ?? false)) {
                continue;
            }

            $offered = self::offeredIds($field->config ?? []);

            foreach ((array) ($answers[$field->key] ?? []) as $id) {
                if (in_array((int) $id, $offered, true)) {
                    $selectedIds[(int) $id] = true;
                }
            }
        }

        return array_values(array_filter(
            $subEvents,
            fn (SubEventContext $subEvent): bool => isset($selectedIds[$subEvent->eventId]),
        ));
    }

    /**
     * @param  list<SubEventContext>  $selected
     *
     * @throws ValidationException
     */
    public function assertNoScheduleConflict(FormVersion $version, array $selected): void
    {
        foreach ($selected as $index => $first) {
            foreach (array_slice($selected, $index + 1) as $second) {
                if ($first->overlaps($second)) {
                    throw ValidationException::withMessages([
                        $this->errorKey($version) => "« {$first->title} » et « {$second->title} » ont lieu en même temps : choisissez l'une des deux sessions.",
                    ]);
                }
            }
        }
    }

    /**
     * Crée les inscriptions des sessions nouvellement cochées, annule
     * celles décochées et remet à jour les noms des autres. Une liste vide
     * annule toutes les sessions.
     *
     * @param  list<SubEventContext>  $selected
     *
     * @throws SubEventFullException
     */
    public function handle(Registration $parent, array $selected): void
    {
        $selectedIds = array_map(fn (SubEventContext $subEvent): int => $subEvent->eventId, $selected);
        $active = $this->activeChildren($parent);
        $keptEventIds = [];

        foreach ($active as $child) {
            if (in_array((int) $child->event_id, $selectedIds, true)) {
                $this->refreshPeople($parent, $child);
                $keptEventIds[] = (int) $child->event_id;
            } else {
                $this->cancel($child);
            }
        }

        foreach ($selected as $subEvent) {
            if (! in_array($subEvent->eventId, $keptEventIds, true)) {
                $this->register($parent, $subEvent);
            }
        }
    }

    private function errorKey(FormVersion $version): string
    {
        return $version->fields->firstWhere('type', FieldType::SubEvents)->key ?? 'sub_events';
    }

    /**
     * @return Collection<int, Registration>
     */
    private function activeChildren(Registration $parent): Collection
    {
        return Registration::query()
            ->where('parent_registration_id', $parent->id)
            ->whereIn('status', [RegistrationStatus::Confirmed->value, RegistrationStatus::Waitlisted->value])
            ->get();
    }

    private function register(Registration $parent, SubEventContext $subEvent): void
    {
        $people = $parent->attendees()->orderBy('position')->get();
        // Clé versionnée : une session décochée puis recochée repart d'une
        // réservation neuve, jamais de l'ancienne déjà libérée.
        $attempt = Registration::query()
            ->where('parent_registration_id', $parent->id)
            ->where('event_id', $subEvent->eventId)
            ->count() + 1;
        $reservationKey = "{$parent->reservation_key}:sub-event:{$subEvent->eventId}:{$attempt}";

        $outcome = $this->reserveCapacity->handle(
            organizationId: $parent->organization_id,
            holderType: 'event',
            holderId: (string) $subEvent->eventId,
            capacityLimit: $subEvent->capacity,
            reservationKey: $reservationKey,
            quantity: max(1, $people->count()),
            allowWaitlist: $subEvent->allowWaitlist,
        );

        if ($outcome->outcome === ReservationOutcome::Rejected) {
            throw SubEventFullException::forSession($subEvent->title);
        }

        $child = Registration::query()->create([
            'organization_id' => $parent->organization_id,
            'event_id' => $subEvent->eventId,
            'form_version_id' => $parent->form_version_id,
            'parent_registration_id' => $parent->id,
            'contact_id' => $parent->contact_id,
            'status' => $outcome->outcome === ReservationOutcome::Accepted ? RegistrationStatus::Confirmed : RegistrationStatus::Waitlisted,
            'reservation_key' => $reservationKey,
            'email' => $parent->email,
            'first_name' => $parent->first_name,
            'last_name' => $parent->last_name,
            'phone_e164' => $parent->phone_e164,
            'registered_at' => CarbonImmutable::now(),
        ]);

        foreach ($people as $person) {
            Attendee::query()->create([
                'organization_id' => $parent->organization_id,
                'registration_id' => $child->id,
                'first_name' => $person->first_name,
                'last_name' => $person->last_name,
                'email' => $person->email,
                'is_primary' => $person->is_primary,
                'position' => $person->position,
            ]);
        }
    }

    /**
     * Les noms modifiés sur l'inscription principale suivent dans chaque
     * session, pour que l'accueil de la session lise les mêmes noms.
     */
    private function refreshPeople(Registration $parent, Registration $child): void
    {
        $child->update([
            'email' => $parent->email,
            'first_name' => $parent->first_name,
            'last_name' => $parent->last_name,
            'phone_e164' => $parent->phone_e164,
        ]);

        foreach ($parent->attendees()->get() as $person) {
            $child->attendees()->where('position', $person->position)->update([
                'first_name' => $person->first_name,
                'last_name' => $person->last_name,
                'email' => $person->email,
            ]);
        }
    }

    private function cancel(Registration $child): void
    {
        $child->update(['status' => RegistrationStatus::Cancelled, 'cancelled_at' => CarbonImmutable::now()]);

        $this->releaseCapacity->handle('event', (string) $child->event_id, $child->reservation_key);
    }
}
