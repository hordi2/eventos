<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Actions\CreateEvent;
use App\Domain\Event\Actions\DuplicateEvent;
use App\Domain\Event\Actions\UpdateEvent;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventType;
use App\Domain\Event\Models\Venue;
use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Event\CreateEventRequest;
use App\Http\Requests\Organizer\Event\DuplicateEventRequest;
use App\Http\Requests\Organizer\Event\UpdateEventRequest;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class EventController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Events/Create', [
            'event' => null,
            'eventTypes' => $this->eventTypeOptions(),
            'timezones' => $this->timezoneOptions(),
            'venues' => $this->venueOptions(),
        ]);
    }

    public function store(CreateEventRequest $request, CreateEvent $action): RedirectResponse
    {
        $event = $action->handle($this->currentOrganization(), $request->user(), $request->validated());

        return redirect()->route('events.edit', $event);
    }

    public function edit(int $event): Response
    {
        $event = $this->findEvent($event);

        Gate::authorize('update', $event);

        return Inertia::render('Events/Create', [
            'event' => $this->presentEvent($event),
            'eventTypes' => $this->eventTypeOptions(),
            'timezones' => $this->timezoneOptions(),
            'venues' => $this->venueOptions(),
        ]);
    }

    public function update(UpdateEventRequest $request, int $event, UpdateEvent $action): RedirectResponse
    {
        $updated = $action->handle($this->findEvent($event), $request->user(), $request->validated());

        return redirect()->route('events.edit', $updated);
    }

    public function duplicate(DuplicateEventRequest $request, int $event, DuplicateEvent $action): RedirectResponse
    {
        $source = $this->findEvent($event);
        $newStartAt = CarbonImmutable::parse($request->validated('new_start_at'), $source->timezone);

        $duplicate = $action->handle($source, $request->user(), $newStartAt);

        return redirect()->route('events.edit', $duplicate);
    }

    private function findEvent(int $id): Event
    {
        return Event::query()->findOrFail($id);
    }

    private function currentOrganization(): Organization
    {
        return Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function eventTypeOptions(): array
    {
        $labels = [
            'conference' => 'Conférence',
            'product_launch' => 'Lancement de produit',
            'seminar' => 'Séminaire',
            'general_assembly' => 'Assemblée générale',
            'kickoff' => 'Kick-off',
            'gala' => 'Gala',
            'fundraiser' => 'Collecte de fonds',
            'graduation' => 'Remise de diplômes',
            'open_house' => 'Journée portes ouvertes',
            'parents_meeting' => 'Réunion de parents',
            'religious' => 'Événement religieux',
            'wedding' => 'Mariage',
            'birthday' => 'Anniversaire',
            'baptism' => 'Baptême',
            'memorial' => 'Deuil ou commémoration',
            'agency' => "Événement d'agence",
            'other' => 'Autre',
        ];

        return array_map(
            fn (EventType $type): array => ['value' => $type->value, 'label' => $labels[$type->value]],
            EventType::cases(),
        );
    }

    /**
     * @return array<string, string>
     */
    private function timezoneOptions(): array
    {
        return [
            'Africa/Kinshasa' => 'Kinshasa (RDC)',
            'Africa/Lubumbashi' => 'Lubumbashi (RDC)',
            'Africa/Brazzaville' => 'Brazzaville (Congo)',
            'Africa/Douala' => 'Douala (Cameroun)',
            'Africa/Abidjan' => "Abidjan (Côte d'Ivoire)",
            'Africa/Dakar' => 'Dakar (Sénégal)',
            'Europe/Paris' => 'Paris (France)',
            'Europe/Brussels' => 'Bruxelles (Belgique)',
            'UTC' => 'UTC',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentEvent(Event $event): array
    {
        $event->loadMissing('venue');

        return [
            'id' => $event->id,
            'title' => $event->title,
            'subtitle' => $event->subtitle,
            'description' => $event->description,
            'type' => $event->type->value,
            'startAt' => $event->start_at->toIso8601String(),
            'endAt' => $event->end_at->toIso8601String(),
            'timezone' => $event->timezone,
            'venueId' => $event->venue_id,
            'venueName' => $event->venue?->name,
            'venueAddress' => $event->venue?->address,
        ];
    }

    /**
     * @return list<array{id: int, name: string, address: string}>
     */
    private function venueOptions(): array
    {
        return Venue::query()
            ->orderBy('name')
            ->get(['id', 'name', 'address'])
            ->map(fn (Venue $venue): array => [
                'id' => $venue->id,
                'name' => $venue->name,
                'address' => $venue->address,
            ])
            ->all();
    }
}
