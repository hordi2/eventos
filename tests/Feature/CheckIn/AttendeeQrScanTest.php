<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventType;
use App\Domain\Form\Actions\CancelRegistration;
use App\Domain\Form\Actions\GenerateAttendeeQrToken;
use App\Domain\Form\Actions\SubmitRegistration;
use App\Domain\Form\Data\AttendeeIdentity;
use App\Domain\Form\Data\CompanionData;
use App\Domain\Form\Data\EventEditPolicy;
use App\Domain\Form\Data\EventRegistrationContext;
use App\Domain\Form\Data\RegistrationSubmissionMetadata;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\Registration;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config(['services.ticket_qr.secret' => 'test-qr-secret-au-moins-256-bits-pour-hs256']);
});

/**
 * Marie Lusala inscrite avec un accompagnant, Paul Kalala, et un agent
 * d'accueil de la même organisation.
 *
 * @return array{organization: Organization, event: Event, doorStaff: User, registration: Registration}
 */
function registrationWithCompanionForCheckIn(): array
{
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent(eventOverrides: ['type' => EventType::Conference]);

    app(CurrentOrganization::class)->set($organization);
    $doorStaff = User::factory()->create();
    Membership::factory()->for($organization)->for($doorStaff)->create(['role' => MembershipRole::DoorStaff]);

    $registration = app(SubmitRegistration::class)->handle(
        new EventRegistrationContext(
            eventId: $event->id,
            organizationId: $organization->id,
            capacity: null,
            allowWaitlist: false,
            registrationOpensAt: null,
            registrationClosesAt: null,
            timezone: $event->timezone,
            registrationClosedMessage: null,
        ),
        Form::query()->where('event_id', $event->id)->firstOrFail()->currentVersion,
        new AttendeeIdentity('marie@example.com', 'Marie', 'Lusala'),
        [],
        new RegistrationSubmissionMetadata,
        (string) Str::uuid(),
        null,
        [new CompanionData('Paul', 'Kalala')],
    )->registration;
    app(CurrentOrganization::class)->clear();

    return ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff, 'registration' => $registration];
}

function companionQrTokenFor(Organization $organization, Registration $registration): string
{
    app(CurrentOrganization::class)->set($organization);
    $token = app(GenerateAttendeeQrToken::class)->handle($registration->companions()->firstOrFail(), CarbonImmutable::now()->addDay());
    app(CurrentOrganization::class)->clear();

    return $token;
}

it('liste chaque accompagnant avec son titulaire au check-in', function (): void {
    ['event' => $event, 'doorStaff' => $doorStaff] = registrationWithCompanionForCheckIn();

    $this->actingAs($doorStaff)->get("/events/{$event->id}/check-in")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('guests', 2)
            ->where('guests.0.name', 'Marie Lusala')
            ->where('guests.1.name', 'Paul Kalala (avec Marie Lusala)'));
});

it('enregistre l\'arrivée d\'un accompagnant par son QR et signale un second scan', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff, 'registration' => $registration] = registrationWithCompanionForCheckIn();
    $token = companionQrTokenFor($organization, $registration);

    $this->actingAs($doorStaff)->postJson("/events/{$event->id}/check-in/scan", ['token' => $token])
        ->assertOk()
        ->assertJsonPath('status', 'accepted')
        ->assertJsonPath('guest.name', 'Paul Kalala (avec Marie Lusala)')
        ->assertJsonPath('guest.checked_in', true);

    $this->actingAs($doorStaff)->postJson("/events/{$event->id}/check-in/scan", ['token' => $token])
        ->assertOk()
        ->assertJsonPath('status', 'conflict');
});

it('refuse le QR d\'une inscription annulée', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff, 'registration' => $registration] = registrationWithCompanionForCheckIn();
    $token = companionQrTokenFor($organization, $registration);

    app(CurrentOrganization::class)->set($organization);
    app(CancelRegistration::class)->handle($registration->fresh(), new EventEditPolicy(true, null, $event->timezone));
    app(CurrentOrganization::class)->clear();

    $this->actingAs($doorStaff)->postJson("/events/{$event->id}/check-in/scan", ['token' => $token])
        ->assertUnprocessable()
        ->assertJsonPath('error', "Cette inscription n'est pas confirmée : annulée, refusée ou en liste d'attente.");
});

it('refuse le QR d\'un invité inscrit à un autre événement', function (): void {
    ['organization' => $organization, 'doorStaff' => $doorStaff, 'registration' => $registration] = registrationWithCompanionForCheckIn();
    $token = companionQrTokenFor($organization, $registration);

    app(CurrentOrganization::class)->set($organization);
    $otherEvent = Event::factory()->for($organization)->published()->create();
    app(CurrentOrganization::class)->clear();

    $this->actingAs($doorStaff)->postJson("/events/{$otherEvent->id}/check-in/scan", ['token' => $token])
        ->assertUnprocessable()
        ->assertJsonPath('error', "Ce QR code n'appartient pas à cet événement.");
});

it('refuse un QR d\'accompagnant falsifié', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff, 'registration' => $registration] = registrationWithCompanionForCheckIn();
    $token = companionQrTokenFor($organization, $registration);

    $this->actingAs($doorStaff)->postJson("/events/{$event->id}/check-in/scan", ['token' => $token.'x'])
        ->assertUnprocessable()
        ->assertJsonPath('error', 'QR code illisible ou falsifié.');
});
