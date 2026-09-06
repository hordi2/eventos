<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Organization\Models\MembershipRole;
use App\Support\Gdpr\ExportContactData;
use App\Support\MultiTenancy\CurrentOrganization;

it('exporte les données d\'un contact, ses consentements et son historique d\'inscription', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    app(CurrentOrganization::class)->set($organization);
    $contact = Contact::factory()->for($organization)->create([
        'first_name' => 'Grace',
        'last_name' => 'Mbuyi',
        'email' => 'grace@example.com',
        'email_consent' => true,
        'email_consent_source' => 'formulaire RSVP',
    ]);
    $event = Event::factory()->for($organization)->published()->create();
    registerContactForEvent($organization, $event, $contact, RegistrationStatus::Confirmed);

    $data = app(ExportContactData::class)->handle($contact);
    app(CurrentOrganization::class)->clear();

    expect($data['contact']['email'])->toBe('grace@example.com');
    expect($data['consentements']['email']['accordé'])->toBeTrue();
    expect($data['consentements']['email']['source'])->toBe('formulaire RSVP');
    expect($data['inscriptions'])->toHaveCount(1);
    expect($data['inscriptions'][0]['statut'])->toBe('confirmed');
    expect($data['inscriptions'][0]['événement_id'])->toBe($event->id);
});
