<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Form\Actions\SubmitRegistration;
use App\Domain\Form\Data\AttendeeIdentity;
use App\Domain\Form\Data\EventRegistrationContext;
use App\Domain\Form\Data\RegistrationSubmissionMetadata;
use App\Domain\Form\Models\Form;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Str;

it('relie automatiquement une nouvelle inscription à une fiche contact', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    app(CurrentOrganization::class)->set($organization);

    $version = Form::query()->where('event_id', $event->id)->first()->fresh()->currentVersion;

    $context = new EventRegistrationContext(
        eventId: $event->id,
        organizationId: $organization->id,
        capacity: $event->capacity,
        allowWaitlist: $event->allow_waitlist,
        registrationOpensAt: $event->registration_opens_at,
        registrationClosesAt: $event->registration_closes_at,
        timezone: $event->timezone,
        registrationClosedMessage: $event->registration_closed_message,
    );

    $result = app(SubmitRegistration::class)->handle(
        $context,
        $version,
        new AttendeeIdentity('nouveau@example.org', 'Jean', 'Kalala'),
        [],
        new RegistrationSubmissionMetadata,
        (string) Str::uuid(),
    );

    $registration = $result->registration->fresh();
    expect($registration->contact_id)->not->toBeNull();

    $contact = Contact::query()->find($registration->contact_id);
    expect($contact->email)->toBe('nouveau@example.org');
    expect($contact->first_name)->toBe('Jean');
});

it('relie une seconde inscription à la même fiche contact existante', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    app(CurrentOrganization::class)->set($organization);
    $existingContact = Contact::factory()->for($organization)->create(['email' => 'repeat@example.org']);

    $version = Form::query()->where('event_id', $event->id)->first()->fresh()->currentVersion;
    $context = new EventRegistrationContext(
        eventId: $event->id,
        organizationId: $organization->id,
        capacity: $event->capacity,
        allowWaitlist: $event->allow_waitlist,
        registrationOpensAt: null,
        registrationClosesAt: null,
        timezone: $event->timezone,
        registrationClosedMessage: null,
    );

    $result = app(SubmitRegistration::class)->handle(
        $context, $version, new AttendeeIdentity('repeat@example.org'), [], new RegistrationSubmissionMetadata, (string) Str::uuid(),
    );

    expect($result->registration->fresh()->contact_id)->toBe($existingContact->id);
    expect(Contact::query()->count())->toBe(1);
});
