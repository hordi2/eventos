<?php

declare(strict_types=1);

use App\Domain\Contact\Actions\CreateContact;
use App\Domain\Contact\Actions\FindOrCreateContact;
use App\Domain\Contact\Actions\UpdateContact;
use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\HouseholdType;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Auth\Access\AuthorizationException;

it('crée un contact et un foyer à la volée', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);

    $contact = app(CreateContact::class)->handle($organization, $admin, [
        'first_name' => 'Grace',
        'last_name' => 'Mbuyi',
        'email' => 'Grace@Example.org',
        'household_name' => 'Famille Mbuyi',
    ]);

    expect($contact->email)->toBe('grace@example.org');
    expect($contact->household->name)->toBe('Famille Mbuyi');
    expect($contact->household->type)->toBe(HouseholdType::Family);
});

it('réutilise le même foyer pour deux contacts du même nom', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);

    $first = app(CreateContact::class)->handle($organization, $admin, ['household_name' => 'Famille Kalala']);
    $second = app(CreateContact::class)->handle($organization, $admin, ['household_name' => 'Famille Kalala']);

    expect($second->household_id)->toBe($first->household_id);
});

it('refuse la création à un rôle sans capacité updateGuests', function (): void {
    [$organization, $viewer] = organizationWithContactRole(MembershipRole::Viewer);

    expect(fn () => app(CreateContact::class)->handle($organization, $viewer, ['email' => 'a@example.org']))
        ->toThrow(AuthorizationException::class);
});

it('horodate un consentement quand il passe de refusé à accordé', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $contact = Contact::factory()->for($organization)->create(['email_consent' => false]);

    $updated = app(UpdateContact::class)->handle($contact, $admin, ['email_consent' => true]);

    expect($updated->email_consent)->toBeTrue();
    expect($updated->email_consent_source)->toBe('organizer');
    expect($updated->email_consent_at)->not->toBeNull();
});

it('conserve la date d\'origine d\'un consentement révoqué', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $contact = Contact::factory()->for($organization)->create([
        'email_consent' => true,
        'email_consent_source' => 'registration',
        'email_consent_at' => now()->subMonth(),
    ]);
    $originalAt = $contact->email_consent_at;

    $updated = app(UpdateContact::class)->handle($contact, $admin, ['email_consent' => false]);

    expect($updated->email_consent)->toBeFalse();
    expect($updated->email_consent_source)->toBe('registration');
    expect($updated->email_consent_at->equalTo($originalAt))->toBeTrue();
});

it('FindOrCreateContact retrouve un contact existant par e-mail sans créer de doublon', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $existing = Contact::factory()->for($organization)->create(['email' => 'double@example.org']);

    $found = app(FindOrCreateContact::class)->handle($organization->id, 'Double@Example.org', 'Autre', 'Nom', null);

    expect($found->id)->toBe($existing->id);
    expect(Contact::query()->count())->toBe(1);
});

it('FindOrCreateContact accorde le consentement e-mail à la création', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $contact = app(FindOrCreateContact::class)->handle($organization->id, 'new@example.org', 'Jean', 'Kalala', null);

    expect($contact->email_consent)->toBeTrue();
    expect($contact->email_consent_source)->toBe('registration');
});
