<?php

declare(strict_types=1);

use App\Domain\CheckIn\Models\CheckIn;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Actions\GenerateTicketQrToken;
use App\Domain\Ticketing\Models\Ticket;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;

it('affiche la page de check-in avec la liste des invités', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent();
    makeCheckedInAttendee($organization, $event);
    makePaidTicket($organization, $event);

    $response = $this->actingAs($doorStaff)->get("/events/{$event->id}/check-in");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('CheckIn/Show')
        ->has('guests', 2));
});

/**
 * makePaidTicket() efface le contexte d'organisation avant de renvoyer le
 * billet (comme toutes les fixtures de tests/Pest.php) : générer le jeton
 * QR après coup doit donc le rétablir soi-même, sans quoi la mise à jour de
 * qr_jti est acceptée par Eloquent mais silencieusement ignorée par la RLS
 * PostgreSQL (aucune ligne ne correspond à la session sans organisation).
 */
function generateQrTokenFor(Ticket $ticket, Organization $organization): string
{
    app(CurrentOrganization::class)->set($organization);
    $token = app(GenerateTicketQrToken::class)->handle($ticket, CarbonImmutable::now()->addDay());
    app(CurrentOrganization::class)->clear();

    return $token;
}

it('scanne un billet valide et l\'enregistre', function (): void {
    config(['services.ticket_qr.secret' => 'test-qr-secret-au-moins-256-bits-pour-hs256']);
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent();
    $ticket = makePaidTicket($organization, $event);
    $token = generateQrTokenFor($ticket, $organization);

    $response = $this->actingAs($doorStaff)->postJson("/events/{$event->id}/check-in/scan", ['token' => $token]);

    $response->assertOk();
    $response->assertJsonPath('status', 'accepted');
    $response->assertJsonPath('guest.checked_in', true);
});

it('signale un conflit sur un second scan du même billet', function (): void {
    config(['services.ticket_qr.secret' => 'test-qr-secret-au-moins-256-bits-pour-hs256']);
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent();
    $ticket = makePaidTicket($organization, $event);
    $token = generateQrTokenFor($ticket, $organization);

    $this->actingAs($doorStaff)->postJson("/events/{$event->id}/check-in/scan", ['token' => $token]);
    $second = $this->actingAs($doorStaff)->postJson("/events/{$event->id}/check-in/scan", ['token' => $token]);

    $second->assertOk();
    $second->assertJsonPath('status', 'conflict');
});

it('refuse un jeton de billet illisible', function (): void {
    config(['services.ticket_qr.secret' => 'test-qr-secret-au-moins-256-bits-pour-hs256']);
    ['doorStaff' => $doorStaff, 'event' => $event] = makeCheckInEvent();

    $response = $this->actingAs($doorStaff)->postJson("/events/{$event->id}/check-in/scan", ['token' => 'pas-un-jwt']);

    $response->assertUnprocessable();
});

it('enregistre manuellement un invité trouvé par recherche', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent();
    $attendee = makeCheckedInAttendee($organization, $event);

    $response = $this->actingAs($doorStaff)->postJson("/events/{$event->id}/check-in/record", [
        'guest_type' => 'attendee',
        'id' => $attendee->id,
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'accepted');

    app(CurrentOrganization::class)->set($organization);
    expect(CheckIn::query()->where('attendee_id', $attendee->id)->count())->toBe(1);
    app(CurrentOrganization::class)->clear();
});

it('refuse l\'accès au check-in web à un rôle sans la capacité checkIn', function (): void {
    ['event' => $event, 'doorStaff' => $viewer] = makeCheckInEvent(MembershipRole::Viewer);

    $response = $this->actingAs($viewer)->get("/events/{$event->id}/check-in");

    $response->assertForbidden();
});
