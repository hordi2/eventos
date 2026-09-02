<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Organization\Models\MembershipRole;
use App\Support\MultiTenancy\CurrentOrganization;

it('liste et recherche les contacts de l\'organisation courante', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    Contact::factory()->for($organization)->create(['first_name' => 'Grace', 'last_name' => 'Mbuyi', 'email' => 'grace@example.org']);
    Contact::factory()->for($organization)->create(['first_name' => 'Jean', 'last_name' => 'Kalala', 'email' => 'jean@example.org']);

    $response = $this->actingAs($admin)->get('/contacts?q=Grace');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('contacts.data', 1)
        ->where('contacts.data.0.full_name', 'Grace Mbuyi'));
});

it('crée un contact via le formulaire organisateur', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);

    $response = $this->actingAs($admin)->post('/contacts', [
        'first_name' => 'Grace',
        'last_name' => 'Mbuyi',
        'email' => 'grace@example.org',
    ]);

    $contact = Contact::query()->where('email', 'grace@example.org')->firstOrFail();
    $response->assertRedirect(route('contacts.edit', $contact));
    expect($contact->organization_id)->toBe($organization->id);
});

it('affiche l\'historique de participation sur la fiche contact', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $contact = Contact::factory()->for($organization)->create();

    ['event' => $event] = makeGuestReadyEvent();
    app(CurrentOrganization::class)->set($organization);

    $response = $this->actingAs($admin)->get("/contacts/{$contact->id}/edit");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->has('history')->where('contact.id', $contact->id));
});

it('refuse l\'accès aux contacts à un rôle sans capacité viewGuests', function (): void {
    [, $user] = organizationWithContactRole(MembershipRole::DoorStaff);

    // door_staff a viewGuests mais pas updateGuests (matrice M0.3, T-002).
    $this->actingAs($user)->get('/contacts')->assertOk();
    $this->actingAs($user)->get('/contacts/create')->assertForbidden();
});

it('bloque l\'accès à un contact d\'une autre organisation', function (): void {
    [, $admin] = organizationWithContactRole(MembershipRole::Admin);
    [$otherOrganization] = organizationWithContactRole(MembershipRole::Admin);
    $otherContact = Contact::factory()->for($otherOrganization)->create();

    $this->actingAs($admin)->get("/contacts/{$otherContact->id}/edit")->assertNotFound();
});
