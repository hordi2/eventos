<?php

declare(strict_types=1);

use App\Domain\CheckIn\Models\SeatAssignment;
use App\Domain\CheckIn\Models\SeatingTable;
use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\Venue;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Organization\Models\Organization;
use App\Support\Messaging\ResolveMergeVariables;
use App\Support\MultiTenancy\CurrentOrganization;

it('résout les variables connues avec les données du contact et de l\'événement', function (): void {
    $organization = Organization::factory()->create(['slug' => 'itaza-events']);
    app(CurrentOrganization::class)->set($organization);
    $venue = Venue::factory()->for($organization)->create(['name' => 'Salle Fleuve Congo']);
    $event = Event::factory()->for($organization)->create([
        'slug' => 'gala-2026',
        'venue_id' => $venue->id,
        'start_at' => '2026-12-10 18:00:00',
        'timezone' => 'Africa/Kinshasa',
    ]);
    $contact = Contact::factory()->for($organization)->create(['first_name' => 'Grace', 'last_name' => 'Mbuyi']);

    $text = 'Bonjour {{first_name}} {{last_name}}, le {{event_date}} à {{event_location}}. Répondez : {{rsvp_link}}';
    $resolved = app(ResolveMergeVariables::class)->resolve($text, $contact, $event);

    expect($resolved)->toContain('Bonjour Grace Mbuyi');
    expect($resolved)->toContain('Salle Fleuve Congo');
    expect($resolved)->toContain('/r/itaza-events/gala-2026');
    expect($resolved)->not->toContain('{{');
});

it('affiche une valeur de repli quand une variable connue n\'a pas de donnée, jamais la balise brute', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $contact = Contact::factory()->for($organization)->create(['first_name' => null]);

    $resolved = app(ResolveMergeVariables::class)->resolve('Bonjour {{first_name}}, {{variable_inexistante}} !', $contact, null);

    expect($resolved)->toBe('Bonjour cher invité,  !');
    expect($resolved)->not->toContain('{{');
});

it('résout un champ personnalisé du contact', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $contact = Contact::factory()->for($organization)->create(['custom_fields' => ['numero_badge' => 'A-042']]);

    $resolved = app(ResolveMergeVariables::class)->resolve('Badge : {{custom_fields.numero_badge}}', $contact, null);

    expect($resolved)->toBe('Badge : A-042');
});

it('indique "En ligne" comme lieu pour un événement en ligne', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $event = Event::factory()->for($organization)->create(['is_online' => true, 'venue_id' => null]);
    $contact = Contact::factory()->for($organization)->create();

    $resolved = app(ResolveMergeVariables::class)->resolve('{{event_location}}', $contact, $event);

    expect($resolved)->toBe('En ligne');
});

it('résout le nom de la table assignée à l\'invité principal du contact', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $event = Event::factory()->for($organization)->create();
    $contact = Contact::factory()->for($organization)->create();
    $registration = registerContactForEvent($organization, $event, $contact, RegistrationStatus::Confirmed);
    $table = SeatingTable::factory()->for($organization)->create(['event_id' => $event->id, 'name' => 'Table 3']);
    SeatAssignment::factory()->for($organization)->create([
        'event_id' => $event->id,
        'seating_table_id' => $table->id,
        'guest_type' => 'attendee',
        'guest_id' => $registration->attendees()->first()->id,
    ]);

    $resolved = app(ResolveMergeVariables::class)->resolve('Vous êtes à la {{table}}', $contact, $event);

    expect($resolved)->toBe('Vous êtes à la Table 3');
});

it('affiche un repli vide pour {{table}} quand le contact n\'a pas encore d\'affectation', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $event = Event::factory()->for($organization)->create();
    $contact = Contact::factory()->for($organization)->create();
    registerContactForEvent($organization, $event, $contact, RegistrationStatus::Confirmed);

    $resolved = app(ResolveMergeVariables::class)->resolve('Table : {{table}}', $contact, $event);

    expect($resolved)->toBe('Table : ');
});
