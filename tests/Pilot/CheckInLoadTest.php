<?php

declare(strict_types=1);

use App\Domain\Organization\Models\MembershipRole;
use Illuminate\Support\Str;

/**
 * T-078 (Événement pilote) : ce test ne remplace pas le vrai événement de
 * 200 à 500 personnes exigé par le ticket — impossible à automatiser, une
 * équipe doit le mener sur le terrain (voir docs/evenement-pilote-*.md).
 * Il vérifie seulement que le backend, chargé avec un volume de données
 * représentatif de cette taille d'événement, n'est pas lui-même la cause
 * d'un dépassement du seuil AC de 8 s par check-in — la marge doit rester
 * très large puisque le temps humain au poste (accueil, geste physique du
 * scan) s'ajoute par-dessus ce temps serveur mesuré ici.
 *
 * Hors des trois testsuites de phpunit.xml (Unit/Feature/Architecture),
 * comme tests/Concurrency et tests/Performance : volume de données élevé,
 * nettement plus lent que la suite par défaut de chaque ticket.
 */
it('traite 300 check-ins un par un, moyenne bien en dessous des 8 s de l\'AC, avec 300 billets déjà en base', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent(MembershipRole::DoorStaff);

    $tickets = collect(range(1, 300))->map(fn () => makePaidTicket($organization, $event));

    $durations = [];

    foreach ($tickets as $ticket) {
        $start = microtime(true);

        $response = $this->actingAs($doorStaff, 'sanctum')->postJson("/api/v1/events/{$event->id}/check-ins", [
            'ticket_id' => $ticket->id,
            'device_local_id' => (string) Str::uuid(),
            'direction' => 'check_in',
            'recorded_at' => now()->toIso8601String(),
        ]);

        $durations[] = microtime(true) - $start;

        $response->assertCreated();
    }

    $average = array_sum($durations) / count($durations);

    expect($average)->toBeLessThan(8.0);
})->group('pilot');

it('resynchronise un lot de 50 check-ins accumulés hors ligne en une seule requête, bien en dessous des 8 s de l\'AC', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent(MembershipRole::DoorStaff);

    // Les 250 premiers billets simulent les invités déjà check-inés plus tôt
    // dans l'événement (volume réaliste en base au moment de la synchronisation),
    // les 50 derniers sont ceux du lot resynchronisé dans cette requête.
    $existingTickets = collect(range(1, 250))->map(fn () => makePaidTicket($organization, $event));
    $batchTickets = collect(range(1, 50))->map(fn () => makePaidTicket($organization, $event));

    foreach ($existingTickets as $ticket) {
        $this->actingAs($doorStaff, 'sanctum')->postJson("/api/v1/events/{$event->id}/check-ins", [
            'ticket_id' => $ticket->id,
            'device_local_id' => (string) Str::uuid(),
            'direction' => 'check_in',
            'recorded_at' => now()->toIso8601String(),
        ])->assertCreated();
    }

    $scans = $batchTickets->map(fn ($ticket) => [
        'ticket_id' => $ticket->id,
        'device_local_id' => (string) Str::uuid(),
        'direction' => 'check_in',
        'recorded_at' => now()->toIso8601String(),
    ])->all();

    $start = microtime(true);

    $response = $this->actingAs($doorStaff, 'sanctum')
        ->postJson("/api/v1/events/{$event->id}/check-ins/sync", ['scans' => $scans]);

    $totalDuration = microtime(true) - $start;

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(50);
    expect($totalDuration / 50)->toBeLessThan(8.0);
})->group('pilot');
