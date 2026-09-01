<?php

declare(strict_types=1);

namespace App\Domain\Event\Actions;

use App\Domain\Event\InvalidSubEventException;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventAccessMode;
use App\Domain\Event\Models\EventStatus;
use App\Domain\Event\Models\EventType;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\OrganizationScope;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CreateEvent
{
    public function __construct(
        private readonly CreateVenue $createVenue,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Organization $organization, User $creator, array $data): Event
    {
        Gate::forUser($creator)->authorize('create', [Event::class, $organization]);

        $this->assertValidTimezone($data['timezone']);

        $startAt = $this->resolveDateTime($data['start_at'], $data['timezone']);
        $endAt = isset($data['end_at'])
            ? $this->resolveDateTime($data['end_at'], $data['timezone'])
            : $startAt->addHours(3);

        return Event::query()->create([
            'organization_id' => $organization->id,
            'created_by' => $creator->id,
            'title' => $data['title'],
            'subtitle' => $data['subtitle'] ?? null,
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? EventType::Other,
            'status' => EventStatus::Draft,
            'slug' => $this->resolveSlug($organization, $data['slug'] ?? $data['title']),
            'start_at' => $startAt,
            'end_at' => $endAt,
            'timezone' => $data['timezone'],
            'is_online' => $data['is_online'] ?? false,
            'online_url' => $data['online_url'] ?? null,
            'venue_id' => $this->resolveVenueId($organization, $creator, $data),
            'parent_event_id' => $this->resolveParentEventId($data),
            'capacity' => $data['capacity'] ?? null,
            'registration_opens_at' => $data['registration_opens_at'] ?? null,
            'registration_closes_at' => $data['registration_closes_at'] ?? null,
            'access_mode' => $data['access_mode'] ?? EventAccessMode::Public,
            'requires_approval' => $data['requires_approval'] ?? false,
            'allow_waitlist' => $data['allow_waitlist'] ?? false,
            'allow_guest_edit' => $data['allow_guest_edit'] ?? false,
            'edit_deadline' => $data['edit_deadline'] ?? null,
            'currency' => $data['currency'] ?? null,
        ]);
    }

    private function assertValidTimezone(string $timezone): void
    {
        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException("\"{$timezone}\" n'est pas un fuseau horaire IANA valide.");
        }
    }

    /**
     * Interprète une chaîne sans fuseau (ex. saisie d'un formulaire, "2026-09-08T14:30")
     * comme une heure locale dans le fuseau de l'événement, jamais celui du serveur
     * (règle 4.3 du CLAUDE.md). Un DateTimeInterface déjà construit (tests, factories)
     * conserve son propre fuseau : Carbon ignore alors le second argument.
     *
     * La conversion explicite en UTC est nécessaire car Eloquent formate une date
     * castée en utilisant le fuseau *courant* de l'objet Carbon fourni, pas UTC :
     * sans ce ->utc(), la colonne recevrait l'heure locale telle quelle.
     */
    private function resolveDateTime(string|DateTimeInterface $value, string $timezone): CarbonImmutable
    {
        return CarbonImmutable::parse($value, $timezone)->utc();
    }

    /**
     * Un événement référence un lieu déjà saisi (venue_id, réutilisation) ou
     * en décrit un nouveau à la volée (venue_name, ...), créé au passage puis
     * réutilisable pour les prochains événements de l'organisation.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveVenueId(Organization $organization, User $creator, array $data): ?int
    {
        if (isset($data['venue_id'])) {
            return (int) $data['venue_id'];
        }

        if (isset($data['venue_name'])) {
            return $this->createVenue->handle($organization, $creator, [
                'name' => $data['venue_name'],
                'address' => $data['venue_address'] ?? '',
                'access_instructions' => $data['venue_access_instructions'] ?? null,
                'parking_info' => $data['venue_parking_info'] ?? null,
            ])->id;
        }

        return null;
    }

    /**
     * Un seul niveau de hiérarchie est autorisé (M1.3 du CDC) : un
     * sous-événement ne peut pas lui-même contenir de sous-événements.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveParentEventId(array $data): ?int
    {
        if (! isset($data['parent_event_id'])) {
            return null;
        }

        $parent = Event::query()->findOrFail($data['parent_event_id']);

        if ($parent->isSubEvent()) {
            throw InvalidSubEventException::nestedSubEvent();
        }

        return $parent->id;
    }

    private function resolveSlug(Organization $organization, string $base, ?int $ignoreEventId = null): string
    {
        $slug = Str::slug($base);
        $original = $slug;
        $suffix = 1;

        while (
            Event::withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $organization->id)
                ->where('slug', $slug)
                ->when($ignoreEventId !== null, fn ($query) => $query->where('id', '!=', $ignoreEventId))
                ->exists()
        ) {
            $slug = "{$original}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
