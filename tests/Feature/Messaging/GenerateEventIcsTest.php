<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\Venue;
use App\Domain\Organization\Models\Organization;
use App\Support\Messaging\GenerateEventIcs;
use App\Support\MultiTenancy\CurrentOrganization;

it('génère un fichier ICS correct, en UTC, avec un rappel', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $venue = Venue::factory()->for($organization)->create(['name' => 'Salle Fleuve Congo', 'address' => '12 Avenue du Fleuve, Kinshasa']);
    $event = Event::factory()->for($organization)->create([
        'title' => 'Gala annuel',
        'venue_id' => $venue->id,
        'start_at' => '2026-12-10 17:00:00',
        'end_at' => '2026-12-10 20:00:00',
        'timezone' => 'Africa/Kinshasa',
    ]);

    $ics = app(GenerateEventIcs::class)->handle($event);

    expect($ics)->toStartWith("BEGIN:VCALENDAR\r\n");
    expect($ics)->toContain("VERSION:2.0\r\n");
    // start_at/end_at déjà en UTC (règle 4.3 du CLAUDE.md) — Africa/Kinshasa
    // n'a pas de décalage sur UTC, donc 17h/20h restent 17h/20h en Z.
    expect($ics)->toContain('DTSTART:20261210T170000Z');
    expect($ics)->toContain('DTEND:20261210T200000Z');
    expect($ics)->toContain('SUMMARY:Gala annuel');
    expect($ics)->toContain('LOCATION:12 Avenue du Fleuve\\, Kinshasa');
    expect($ics)->toContain("BEGIN:VALARM\r\n");
    expect($ics)->toContain('TRIGGER:-PT1H');
    expect($ics)->toContain("END:VCALENDAR\r\n");
});

it('replie les lignes de plus de 75 caractères', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $event = Event::factory()->for($organization)->create([
        'title' => str_repeat('Un très long titre pour cet événement qui dépasse largement la limite ', 2),
    ]);

    $ics = app(GenerateEventIcs::class)->handle($event);

    foreach (explode("\r\n", $ics) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75);
    }
});
