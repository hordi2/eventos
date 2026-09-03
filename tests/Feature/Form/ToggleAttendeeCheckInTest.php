<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Attendee;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;

function makeConfirmedAttendee(Organization $organization): Attendee
{
    $event = Event::factory()->for($organization)->create();
    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'created_by' => User::factory()->create()->id,
    ]);
    $version = FormVersion::factory()->create(['organization_id' => $organization->id, 'form_id' => $form->id]);
    $contact = Contact::factory()->for($organization)->create();

    $registration = Registration::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'form_version_id' => $version->id,
        'contact_id' => $contact->id,
        'status' => RegistrationStatus::Confirmed,
    ]);

    return Attendee::factory()->create([
        'organization_id' => $organization->id,
        'registration_id' => $registration->id,
        'is_primary' => true,
    ]);
}

it('marque un participant présent puis annule le pointage', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $attendee = makeConfirmedAttendee($organization);
    expect($attendee->checked_in_at)->toBeNull();

    $this->actingAs($admin)->post(route('attendees.toggle-check-in', $attendee))->assertRedirect();
    expect($attendee->fresh()->checked_in_at)->not->toBeNull();

    $this->actingAs($admin)->post(route('attendees.toggle-check-in', $attendee))->assertRedirect();
    expect($attendee->fresh()->checked_in_at)->toBeNull();
});

it('refuse le pointage à un rôle sans capacité checkIn', function (): void {
    [$organization, $viewer] = organizationWithContactRole(MembershipRole::Viewer);
    $attendee = makeConfirmedAttendee($organization);

    $this->actingAs($viewer)->post(route('attendees.toggle-check-in', $attendee))->assertForbidden();
});
