<?php

declare(strict_types=1);

use App\Domain\CheckIn\Models\CheckIn;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Ticketing\Actions\GenerateTicketQrToken;
use App\Domain\Ticketing\Models\Ticket;
use App\Domain\Ticketing\Models\TicketStatus;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
use Firebase\JWT\JWT;
use Illuminate\Support\Str;

// makeCheckInEvent/makeCheckedInAttendee/makePaidTicket : voir tests/Pest.php
// (partagées avec le check-in web de secours, T-062).

function ticketQrToken(Ticket $ticket): string
{
    return app(GenerateTicketQrToken::class)->handle($ticket, CarbonImmutable::now()->addDay());
}

/**
 * Un jeton correctement formé (même structure qu'un vrai), mais signé avec
 * une clé différente de celle du serveur : simule un billet réclamé par un
 * attaquant sans jamais avoir eu le vrai jeton signé.
 */
function forgedTicketQrToken(Ticket $ticket): string
{
    return JWT::encode([
        'tid' => $ticket->id,
        'jti' => (string) Str::uuid(),
        'iat' => CarbonImmutable::now()->timestamp,
        'exp' => CarbonImmutable::now()->addDay()->timestamp,
    ], 'une-cle-totalement-differente-de-celle-du-serveur', 'HS256');
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

it('accepte un check-in scanné dont le jeton QR est valide', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent();
    $ticket = makePaidTicket($organization, $event);
    app(CurrentOrganization::class)->set($organization);
    $token = ticketQrToken($ticket);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($doorStaff, 'sanctum')->postJson("/api/v1/events/{$event->id}/check-ins", [
        'ticket_id' => $ticket->id,
        'qr_token' => $token,
        'device_local_id' => (string) Str::uuid(),
        'direction' => 'check_in',
        'recorded_at' => now()->toIso8601String(),
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.status', 'accepted');
    $response->assertJsonPath('data.ticket_id', $ticket->id);
});

it('rejette un check-in dont le jeton QR est forgé, même s\'il revendique un billet réel de cet événement', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent();
    $ticket = makePaidTicket($organization, $event);

    $response = $this->actingAs($doorStaff, 'sanctum')->postJson("/api/v1/events/{$event->id}/check-ins", [
        // ticket_id réel et valide pour cet événement, mais un jeton forgé :
        // c'est justement ce que GuestExistsForEvent seul ne peut pas
        // détecter — seule la vérification du jeton (signature) le peut.
        'ticket_id' => $ticket->id,
        'qr_token' => forgedTicketQrToken($ticket),
        'device_local_id' => (string) Str::uuid(),
        'direction' => 'check_in',
        'recorded_at' => now()->toIso8601String(),
    ]);

    $response->assertUnprocessable();

    app(CurrentOrganization::class)->set($organization);
    expect(CheckIn::query()->where('ticket_id', $ticket->id)->exists())->toBeFalse();
    app(CurrentOrganization::class)->clear();
});

it('rejette proprement un QR illisible, trois segments mais sans JSON valide, sans erreur serveur', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent();
    $ticket = makePaidTicket($organization, $event);

    $response = $this->actingAs($doorStaff, 'sanctum')->postJson("/api/v1/events/{$event->id}/check-ins", [
        'ticket_id' => $ticket->id,
        'qr_token' => 'header.'.base64_encode(json_encode(['tid' => $ticket->id])).'.signature-inventee',
        'device_local_id' => (string) Str::uuid(),
        'direction' => 'check_in',
        'recorded_at' => now()->toIso8601String(),
    ]);

    $response->assertUnprocessable();
});

it('rejette un QR à trois segments mais trop court pour être un jeton', function (): void {
    ['event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent();

    $response = $this->actingAs($doorStaff, 'sanctum')->postJson("/api/v1/events/{$event->id}/check-ins", [
        'ticket_id' => 1,
        'qr_token' => 'ceci-ne-ressemble-meme-pas-a-un-jwt',
        'device_local_id' => (string) Str::uuid(),
        'direction' => 'check_in',
        'recorded_at' => now()->toIso8601String(),
    ]);

    $response->assertUnprocessable();
});

it('rejette un check-in dont le billet a déjà été annulé, même avec un jeton signé valide', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent();
    $ticket = makePaidTicket($organization, $event);
    app(CurrentOrganization::class)->set($organization);
    $token = ticketQrToken($ticket);
    $ticket->update(['status' => TicketStatus::Cancelled]);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($doorStaff, 'sanctum')->postJson("/api/v1/events/{$event->id}/check-ins", [
        'ticket_id' => $ticket->id,
        'qr_token' => $token,
        'device_local_id' => (string) Str::uuid(),
        'direction' => 'check_in',
        'recorded_at' => now()->toIso8601String(),
    ]);

    $response->assertUnprocessable();

    app(CurrentOrganization::class)->set($organization);
    expect(CheckIn::query()->where('ticket_id', $ticket->id)->exists())->toBeFalse();
    app(CurrentOrganization::class)->clear();
});

it('rejette un jeton QR réémis (ancien jeton d\'un billet dont le QR a été renouvelé)', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent();
    $ticket = makePaidTicket($organization, $event);
    app(CurrentOrganization::class)->set($organization);
    $staleToken = ticketQrToken($ticket);
    // Un nouveau jti réémis invalide l'ancien jeton (ReissueTicketQr) : on
    // simule directement la mise à jour qu'il effectuerait.
    $ticket->update(['qr_jti' => (string) Str::uuid()]);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($doorStaff, 'sanctum')->postJson("/api/v1/events/{$event->id}/check-ins", [
        'ticket_id' => $ticket->id,
        'qr_token' => $staleToken,
        'device_local_id' => (string) Str::uuid(),
        'direction' => 'check_in',
        'recorded_at' => now()->toIso8601String(),
    ]);

    $response->assertUnprocessable();
});

it('rejette un jeton QR forgé dans un lot de resynchronisation, sans bloquer les autres scans', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent();
    $goodTicket = makePaidTicket($organization, $event);
    $badTicket = makePaidTicket($organization, $event);
    app(CurrentOrganization::class)->set($organization);
    $goodToken = ticketQrToken($goodTicket);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($doorStaff, 'sanctum')->postJson("/api/v1/events/{$event->id}/check-ins/sync", [
        'scans' => [
            [
                'ticket_id' => $goodTicket->id,
                'qr_token' => $goodToken,
                'device_local_id' => (string) Str::uuid(),
                'direction' => 'check_in',
                'recorded_at' => now()->toIso8601String(),
            ],
            [
                'ticket_id' => $badTicket->id,
                'qr_token' => forgedTicketQrToken($badTicket),
                'device_local_id' => (string) Str::uuid(),
                'direction' => 'check_in',
                'recorded_at' => now()->toIso8601String(),
            ],
        ],
    ]);

    $response->assertOk();
    expect($response->json('data.0.status'))->toBe('accepted');
    expect($response->json('data.1.status'))->toBe('rejected');
    expect($response->json('data.1.check_in_id'))->toBeNull();

    app(CurrentOrganization::class)->set($organization);
    expect(CheckIn::query()->where('ticket_id', $goodTicket->id)->exists())->toBeTrue();
    expect(CheckIn::query()->where('ticket_id', $badTicket->id)->exists())->toBeFalse();
    app(CurrentOrganization::class)->clear();
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
