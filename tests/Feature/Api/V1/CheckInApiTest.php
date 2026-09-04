<?php

declare(strict_types=1);

use App\Domain\CheckIn\Models\CheckIn;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Attendee;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderItem;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\Ticket;
use App\Domain\Ticketing\Models\TicketStatus;
use App\Domain\Ticketing\Models\TicketType;
use App\Models\User;
use App\Support\Money;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Str;

/**
 * @return array{organization: Organization, event: Event, doorStaff: User}
 */
function makeCheckInEvent(MembershipRole $role = MembershipRole::DoorStaff): array
{
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $event = Event::factory()->for($organization)->published()->create();
    $doorStaff = User::factory()->create();
    Membership::factory()->for($organization)->for($doorStaff)->create(['role' => $role]);
    app(CurrentOrganization::class)->clear();

    return ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff];
}

/**
 * Chaîne Form -> FormVersion -> Registration entièrement explicite : les
 * factories imbriquées de FormVersionFactory/FormFactory génèrent chacune
 * leur propre organisation et leur propre événement par défaut, ce qui
 * viole la RLS si on ne les fournit pas nous-mêmes (piège déjà documenté
 * dans tests/Pest.php pour makeConfirmedAttendee/organizationWithContactRole).
 */
function makeCheckedInAttendee(Organization $organization, Event $event): Attendee
{
    app(CurrentOrganization::class)->set($organization);
    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'created_by' => User::factory()->create()->id,
    ]);
    $version = FormVersion::factory()->create(['organization_id' => $organization->id, 'form_id' => $form->id]);
    $registration = Registration::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'form_version_id' => $version->id,
    ]);
    $attendee = Attendee::factory()->create([
        'organization_id' => $organization->id,
        'registration_id' => $registration->id,
        'is_primary' => true,
    ]);
    app(CurrentOrganization::class)->clear();

    return $attendee;
}

function makePaidTicket(Organization $organization, Event $event): Ticket
{
    app(CurrentOrganization::class)->set($organization);
    $ticketType = TicketType::factory()->for($organization)->create(['event_id' => $event->id]);
    $tier = PriceTier::factory()->for($ticketType)->for($organization)->create(['amount' => Money::fromMinorUnits(1000, 'EUR')]);
    $order = Order::factory()->for($organization)->create(['event_id' => $event->id, 'status' => OrderStatus::Paid]);
    $item = OrderItem::factory()->for($organization)->for($order)->create(['ticket_type_id' => $ticketType->id, 'price_tier_id' => $tier->id, 'quantity' => 1]);
    $ticket = Ticket::factory()->for($organization)->create(['order_item_id' => $item->id, 'ticket_type_id' => $ticketType->id, 'status' => TicketStatus::Valid]);
    app(CurrentOrganization::class)->clear();

    return $ticket;
}

it('authentifie un poste de check-in et renvoie un jeton', function (): void {
    ['doorStaff' => $doorStaff] = makeCheckInEvent();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $doorStaff->email,
        'password' => 'password',
        'device_name' => 'iPad Entrée principale',
    ]);

    $response->assertCreated();
    $response->assertJsonStructure(['token']);
});

it('refuse la connexion avec un mot de passe invalide', function (): void {
    ['doorStaff' => $doorStaff] = makeCheckInEvent();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $doorStaff->email,
        'password' => 'mauvais-mot-de-passe',
        'device_name' => 'iPad Entrée principale',
    ]);

    $response->assertUnprocessable();
});

it('renvoie la liste des invités attendus, RSVP et billets payés confondus', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent();
    $attendee = makeCheckedInAttendee($organization, $event);
    $ticket = makePaidTicket($organization, $event);

    $response = $this->actingAs($doorStaff, 'sanctum')->getJson("/api/v1/events/{$event->id}/guests");

    $response->assertOk();
    $guestTypes = collect($response->json('data'))->pluck('guest_type')->all();
    expect($guestTypes)->toContain('attendee', 'ticket');
});

it('filtre la liste des invités par recherche', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent();
    $attendee = makeCheckedInAttendee($organization, $event);
    makePaidTicket($organization, $event);

    $response = $this->actingAs($doorStaff, 'sanctum')->getJson("/api/v1/events/{$event->id}/guests?q={$attendee->email}");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('enregistre un check-in et le marque accepté', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent();
    $ticket = makePaidTicket($organization, $event);

    $response = $this->actingAs($doorStaff, 'sanctum')->postJson("/api/v1/events/{$event->id}/check-ins", [
        'ticket_id' => $ticket->id,
        'device_local_id' => (string) Str::uuid(),
        'direction' => 'check_in',
        'recorded_at' => now()->toIso8601String(),
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.status', 'accepted');
});

it('signale un conflit quand deux postes scannent le même billet', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent();
    $ticket = makePaidTicket($organization, $event);

    $first = $this->actingAs($doorStaff, 'sanctum')->postJson("/api/v1/events/{$event->id}/check-ins", [
        'ticket_id' => $ticket->id,
        'device_local_id' => (string) Str::uuid(),
        'direction' => 'check_in',
        'recorded_at' => now()->toIso8601String(),
    ]);
    $first->assertJsonPath('data.status', 'accepted');

    $second = $this->actingAs($doorStaff, 'sanctum')->postJson("/api/v1/events/{$event->id}/check-ins", [
        'ticket_id' => $ticket->id,
        'device_local_id' => (string) Str::uuid(),
        'direction' => 'check_in',
        'recorded_at' => now()->addSecond()->toIso8601String(),
    ]);
    $second->assertJsonPath('data.status', 'conflict');
});

it('resynchronise un lot de check-ins de façon idempotente via device_local_id', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent();
    $ticket = makePaidTicket($organization, $event);
    $deviceLocalId = (string) Str::uuid();

    $payload = [
        'scans' => [
            [
                'ticket_id' => $ticket->id,
                'device_local_id' => $deviceLocalId,
                'direction' => 'check_in',
                'recorded_at' => now()->toIso8601String(),
            ],
        ],
    ];

    $first = $this->actingAs($doorStaff, 'sanctum')->postJson("/api/v1/events/{$event->id}/check-ins/sync", $payload);
    $second = $this->actingAs($doorStaff, 'sanctum')->postJson("/api/v1/events/{$event->id}/check-ins/sync", $payload);

    $first->assertOk();
    $second->assertOk();
    expect($first->json('data.0.check_in_id'))->toBe($second->json('data.0.check_in_id'));

    app(CurrentOrganization::class)->set($organization);
    expect(CheckIn::query()->where('device_local_id', $deviceLocalId)->count())->toBe(1);
    app(CurrentOrganization::class)->clear();
});

it("refuse un check-in pour un billet qui n'appartient pas à l'événement", function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent();
    ['organization' => $otherOrganization, 'event' => $otherEvent] = makeCheckInEvent();
    $foreignTicket = makePaidTicket($otherOrganization, $otherEvent);

    $response = $this->actingAs($doorStaff, 'sanctum')->postJson("/api/v1/events/{$event->id}/check-ins", [
        'ticket_id' => $foreignTicket->id,
        'device_local_id' => (string) Str::uuid(),
        'direction' => 'check_in',
        'recorded_at' => now()->toIso8601String(),
    ]);

    $response->assertUnprocessable();
});

it("refuse l'accès au check-in à un utilisateur sans lien avec l'organisation (404, pour ne pas révéler l'existence de l'événement)", function (): void {
    ['event' => $event] = makeCheckInEvent();
    $stranger = User::factory()->create();

    $response = $this->actingAs($stranger, 'sanctum')->getJson("/api/v1/events/{$event->id}/guests");

    $response->assertNotFound();
});

it("refuse l'accès au check-in à un membre sans l'habilitation checkIn", function (): void {
    ['organization' => $organization, 'event' => $event] = makeCheckInEvent();
    $viewer = User::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    Membership::factory()->for($organization)->for($viewer)->create(['role' => MembershipRole::Viewer]);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($viewer, 'sanctum')->getJson("/api/v1/events/{$event->id}/guests");

    $response->assertForbidden();
});
