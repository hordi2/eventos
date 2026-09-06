<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Organization\Models\MembershipRole;
use App\Support\MultiTenancy\CurrentOrganization;

it('télécharge l\'export JSON complet d\'un contact', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    app(CurrentOrganization::class)->set($organization);
    $contact = Contact::factory()->for($organization)->create(['email' => 'grace@example.com']);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($owner)->get("/contacts/{$contact->id}/export");

    $response->assertOk();
    $response->assertHeader('content-disposition', "attachment; filename=\"contact-{$contact->id}-donnees.json\"");
    expect($response->json('contact.email'))->toBe('grace@example.com');
});

it('anonymise un contact via la route dédiée', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    app(CurrentOrganization::class)->set($organization);
    $contact = Contact::factory()->for($organization)->create(['email' => 'grace@example.com']);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($owner)->post("/contacts/{$contact->id}/anonymize");

    $response->assertRedirect('/contacts');

    app(CurrentOrganization::class)->set($organization);
    expect(Contact::withTrashed()->findOrFail($contact->id)->email)->toBeNull();
    app(CurrentOrganization::class)->clear();
});

it('refuse l\'anonymisation à un rôle sans capacité updateGuests', function (): void {
    ['organization' => $organization, 'doorStaff' => $viewer] = makeCheckInEvent(MembershipRole::Viewer);
    app(CurrentOrganization::class)->set($organization);
    $contact = Contact::factory()->for($organization)->create();
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($viewer)->post("/contacts/{$contact->id}/anonymize");

    $response->assertForbidden();
});

it('affiche le registre des traitements avec la durée de conservation configurée', function (): void {
    config(['gdpr.contact_retention_months' => 24]);
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->get('/compliance/register');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Compliance/Register')->has('activities', 5));
});

it('télécharge le registre des traitements en PDF', function (): void {
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->get('/compliance/register/pdf');

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});
