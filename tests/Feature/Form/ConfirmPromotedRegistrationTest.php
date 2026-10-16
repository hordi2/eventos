<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Organization\Models\Organization;
use App\Listeners\ConfirmPromotedRegistration;
use App\Mail\RegistrationDecisionMail;
use App\Models\User;
use App\Support\Capacity\Events\WaitlistEntryPromoted;
use App\Support\Capacity\Models\WaitlistEntry;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\Mail;

/**
 * event_id/form_version_id sont des clés étrangères réelles : chaque niveau
 * doit explicitement partager la même organisation, sans quoi les factories
 * imbriquées en créeraient chacune une nouvelle, rejetée par la RLS.
 */
function makeWaitlistedRegistration(Organization $organization, string $reservationKey): Registration
{
    $event = Event::factory()->for($organization)->create();
    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'created_by' => User::factory()->create()->id,
    ]);
    $version = FormVersion::factory()->create(['organization_id' => $organization->id, 'form_id' => $form->id]);

    return Registration::factory()->waitlisted()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'form_version_id' => $version->id,
        'reservation_key' => $reservationKey,
    ]);
}

it('confirme l\'inscription liée à une entrée de liste d\'attente promue', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $registration = makeWaitlistedRegistration($organization, 'promo-key');
    $entry = WaitlistEntry::factory()->for($organization)->create(['holder_type' => 'event', 'reservation_key' => 'promo-key']);

    app(ConfirmPromotedRegistration::class)->handle(new WaitlistEntryPromoted($entry));

    expect($registration->fresh()->status->value)->toBe('confirmed');
});

it('prévient l\'invité par e-mail quand sa place passe de liste d\'attente à confirmée', function (): void {
    Mail::fake();
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $registration = makeWaitlistedRegistration($organization, 'promo-key-confirmee');
    $entry = WaitlistEntry::factory()->for($organization)->create(['holder_type' => 'event', 'reservation_key' => 'promo-key-confirmee']);

    app(ConfirmPromotedRegistration::class)->handle(new WaitlistEntryPromoted($entry));

    expect($registration->fresh()->status->value)->toBe('confirmed');
    Mail::assertQueued(RegistrationDecisionMail::class, fn (RegistrationDecisionMail $mail): bool => $mail->approved);
});

it('ne prévient pas l\'invité quand la promotion ne mène qu\'à une place en attente de validation', function (): void {
    Mail::fake();
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $event = Event::factory()->for($organization)->create(['requires_approval' => true]);
    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'created_by' => User::factory()->create()->id,
    ]);
    $version = FormVersion::factory()->create(['organization_id' => $organization->id, 'form_id' => $form->id]);
    $registration = Registration::factory()->waitlisted()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'form_version_id' => $version->id,
        'reservation_key' => 'promo-key-attente',
    ]);
    $entry = WaitlistEntry::factory()->for($organization)->create(['holder_type' => 'event', 'reservation_key' => 'promo-key-attente']);

    app(ConfirmPromotedRegistration::class)->handle(new WaitlistEntryPromoted($entry));

    expect($registration->fresh()->status->value)->toBe('pending');
    Mail::assertNothingQueued();
});

it('ignore une promotion qui ne concerne pas une capacité d\'événement', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $registration = makeWaitlistedRegistration($organization, 'option-key');
    $entry = WaitlistEntry::factory()->for($organization)->create(['holder_type' => 'form_field_option', 'reservation_key' => 'option-key']);

    app(ConfirmPromotedRegistration::class)->handle(new WaitlistEntryPromoted($entry));

    expect($registration->fresh()->status->value)->toBe('waitlisted');
});
