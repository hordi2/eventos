<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Organization\Models\AuditLog;
use App\Domain\Organization\Models\MembershipRole;
use App\Support\Gdpr\AnonymizeContact;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Auth\Access\AuthorizationException;

it('anonymise un contact et ses inscriptions, en gardant le statut et la date', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    app(CurrentOrganization::class)->set($organization);
    $contact = Contact::factory()->for($organization)->create(['first_name' => 'Grace', 'last_name' => 'Mbuyi', 'email' => 'grace@example.com']);
    $event = Event::factory()->for($organization)->published()->create();
    $registration = registerContactForEvent($organization, $event, $contact, RegistrationStatus::Confirmed);

    app(AnonymizeContact::class)->handle($contact, $admin);

    $freshContact = Contact::withTrashed()->find($contact->id);
    expect($freshContact->first_name)->toBe('Contact');
    expect($freshContact->email)->toBeNull();
    expect($freshContact->trashed())->toBeTrue();

    $freshRegistration = $registration->fresh();
    expect($freshRegistration->email)->toBe('');
    expect($freshRegistration->first_name)->toBe('Invité');
    // Ce qui doit être conservé (agrégats/statistiques, règle 4.5 du CLAUDE.md) :
    expect($freshRegistration->status->value)->toBe('confirmed');
    expect($freshRegistration->event_id)->toBe($event->id);
    app(CurrentOrganization::class)->clear();

    expect(AuditLog::query()->where('action', 'contact.anonymized')->where('subject_id', $contact->id)->exists())->toBeTrue();
});

it('refuse l\'anonymisation à un rôle sans capacité updateGuests', function (): void {
    [$organization, $viewer] = organizationWithContactRole(MembershipRole::Viewer);
    app(CurrentOrganization::class)->set($organization);
    $contact = Contact::factory()->for($organization)->create();
    app(CurrentOrganization::class)->clear();

    expect(fn () => app(AnonymizeContact::class)->handle($contact, $viewer))
        ->toThrow(AuthorizationException::class);
});
