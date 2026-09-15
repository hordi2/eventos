<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\CancelRegistration;
use App\Domain\Form\Actions\CreateForm;
use App\Domain\Form\Actions\PublishFormVersion;
use App\Domain\Form\Actions\SubmitRegistration;
use App\Domain\Form\Actions\UpdateRegistration;
use App\Domain\Form\Data\AttendeeIdentity;
use App\Domain\Form\Data\CompanionData;
use App\Domain\Form\Data\EventEditPolicy;
use App\Domain\Form\Data\EventRegistrationContext;
use App\Domain\Form\Data\RegistrationSubmissionMetadata;
use App\Domain\Form\Models\Attendee;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Form\SubEventFullException;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\Capacity\Models\CapacityHold;
use App\Support\Capacity\Models\CapacityHoldStatus;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Registration\BuildSubEventContexts;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Un événement principal et trois sessions : un dîner (2 places), un atelier
 * le lendemain (1 place, liste d'attente) et un cocktail qui chevauche le dîner.
 *
 * @return array{organization: Organization, parent: Event, dinner: Event, workshop: Event, cocktail: Event, version: FormVersion, context: EventRegistrationContext}
 */
function eventWithSessions(): array
{
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);

    $start = CarbonImmutable::parse('2026-12-10 18:00', 'UTC');
    $parent = Event::factory()->for($organization)->create(['start_at' => $start, 'end_at' => $start->addDays(2), 'timezone' => 'UTC']);
    $session = fn (array $attributes): Event => Event::factory()->for($organization)->create(['parent_event_id' => $parent->id, 'timezone' => 'UTC', ...$attributes]);

    $dinner = $session(['title' => 'Dîner de gala', 'start_at' => $start, 'end_at' => $start->addHours(3), 'capacity' => 2]);
    $workshop = $session(['title' => 'Atelier photo', 'start_at' => $start->addDay(), 'end_at' => $start->addDay()->addHours(2), 'capacity' => 1, 'allow_waitlist' => true]);
    $cocktail = $session(['title' => 'Cocktail', 'start_at' => $start->addHour(), 'end_at' => $start->addHours(2)]);

    $form = app(CreateForm::class)->handle($organization, $parent->id, $admin, [
        'name' => 'Inscription',
        'fields' => [[
            'key' => 'sessions',
            'type' => 'sub_events',
            'label' => 'Vos sessions',
            'config' => ['sub_events' => [
                ['id' => $dinner->id, 'title' => 'Dîner de gala'],
                ['id' => $workshop->id, 'title' => 'Atelier photo'],
                ['id' => $cocktail->id, 'title' => 'Cocktail'],
            ]],
        ]],
    ]);
    app(PublishFormVersion::class)->handle($form, $admin);

    return [
        'organization' => $organization,
        'parent' => $parent,
        'dinner' => $dinner,
        'workshop' => $workshop,
        'cocktail' => $cocktail,
        'version' => $form->fresh()->currentVersion,
        'context' => new EventRegistrationContext(
            eventId: $parent->id,
            organizationId: $organization->id,
            capacity: null,
            allowWaitlist: false,
            registrationOpensAt: null,
            registrationClosesAt: null,
            timezone: 'UTC',
            registrationClosedMessage: null,
            subEvents: app(BuildSubEventContexts::class)->handle($parent),
        ),
    ];
}

/**
 * @param  array<string, mixed>  $setup
 * @param  list<int>  $sessionIds
 * @param  list<CompanionData>  $companions
 */
function registerForSessions(array $setup, string $email, array $sessionIds, array $companions = []): Registration
{
    return app(SubmitRegistration::class)->handle(
        $setup['context'],
        $setup['version'],
        new AttendeeIdentity($email, 'Marie', 'Lusala'),
        ['sessions' => array_map(strval(...), $sessionIds)],
        new RegistrationSubmissionMetadata,
        (string) Str::uuid(),
        null,
        $companions,
    )->registration;
}

function heldPlaces(Event $session): int
{
    return (int) CapacityHold::query()
        ->where('holder_type', 'event')
        ->where('holder_id', (string) $session->id)
        ->where('status', CapacityHoldStatus::Held)
        ->sum('quantity');
}

it('inscrit le groupe à chaque session cochée, sur la capacité de la session', function (): void {
    $setup = eventWithSessions();

    $registration = registerForSessions($setup, 'marie@example.com', [$setup['dinner']->id, $setup['workshop']->id], [new CompanionData('Paul')]);

    $sessions = Registration::query()->where('parent_registration_id', $registration->id)->orderBy('event_id')->get();
    expect($sessions->pluck('event_id')->all())->toBe([$setup['dinner']->id, $setup['workshop']->id]);
    expect($sessions[0]->status)->toBe(RegistrationStatus::Confirmed);
    // Deux personnes pour une seule place : le groupe passe en liste d'attente.
    expect($sessions[1]->status)->toBe(RegistrationStatus::Waitlisted);
    expect(Attendee::query()->where('registration_id', $sessions[0]->id)->orderBy('position')->pluck('first_name')->all())->toBe(['Marie', 'Paul']);
    expect(heldPlaces($setup['dinner']))->toBe(2);
});

it('refuse une session complète sans liste d\'attente et n\'enregistre rien', function (): void {
    $setup = eventWithSessions();
    registerForSessions($setup, 'premiere@example.com', [$setup['dinner']->id], [new CompanionData('Paul')]);

    expect(fn () => registerForSessions($setup, 'seconde@example.com', [$setup['dinner']->id]))
        ->toThrow(SubEventFullException::class);
    expect(Registration::query()->where('email', 'seconde@example.com')->exists())->toBeFalse();
});

it('refuse deux sessions qui ont lieu en même temps', function (): void {
    $setup = eventWithSessions();

    try {
        registerForSessions($setup, 'marie@example.com', [$setup['dinner']->id, $setup['cocktail']->id]);
        $this->fail('Le chevauchement aurait dû être refusé.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['sessions'][0])->toContain('ont lieu en même temps');
    }
});

it('annule les sessions avec l\'inscription principale et libère leurs places', function (): void {
    $setup = eventWithSessions();
    $registration = registerForSessions($setup, 'marie@example.com', [$setup['dinner']->id]);

    app(CancelRegistration::class)->handle($registration, new EventEditPolicy(true, null, 'UTC'));

    expect(Registration::query()->where('parent_registration_id', $registration->id)->firstOrFail()->status)->toBe(RegistrationStatus::Cancelled);
    expect(heldPlaces($setup['dinner']))->toBe(0);
});

it('met les sessions à jour quand l\'invité change son choix, y compris en recochant une session', function (): void {
    $setup = eventWithSessions();
    $registration = registerForSessions($setup, 'marie@example.com', [$setup['dinner']->id]);
    $choose = fn (array $sessionIds) => app(UpdateRegistration::class)->handle(
        $registration->fresh(),
        new EventEditPolicy(true, null, 'UTC'),
        new AttendeeIdentity('marie@example.com', 'Marie', 'Lusala'),
        ['sessions' => array_map(strval(...), $sessionIds)],
        null,
        [],
        $setup['context']->subEvents,
    );

    $choose([$setup['workshop']->id]);
    expect(heldPlaces($setup['dinner']))->toBe(0);

    $choose([$setup['dinner']->id, $setup['workshop']->id]);

    $active = Registration::query()
        ->where('parent_registration_id', $registration->id)
        ->whereIn('status', [RegistrationStatus::Confirmed->value, RegistrationStatus::Waitlisted->value])
        ->orderBy('event_id')
        ->pluck('event_id')
        ->all();
    expect($active)->toBe([$setup['dinner']->id, $setup['workshop']->id]);
    expect(heldPlaces($setup['dinner']))->toBe(1);
});
