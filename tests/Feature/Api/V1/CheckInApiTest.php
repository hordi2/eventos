<?php

declare(strict_types=1);

use App\Domain\CheckIn\Models\CheckIn;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Str;

// makeCheckInEvent/makeCheckedInAttendee/makePaidTicket : voir tests/Pest.php
// (partagées avec le check-in web de secours, T-062).

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
