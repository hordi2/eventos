<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Form\Events\RegistrationCreated;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Organization\Actions\SetRegistrationNotificationPreference;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Mail\OrganizerRegistrationNotificationMail;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\Mail;

function createRegistrationFor(Organization $organization, Event $event): Registration
{
    app(CurrentOrganization::class)->set($organization);
    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'created_by' => User::factory()->create()->id,
    ]);
    $version = FormVersion::factory()->create(['organization_id' => $organization->id, 'form_id' => $form->id]);
    $registration = Registration::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'form_version_id' => $version->id,
    ]);
    app(CurrentOrganization::class)->clear();

    return $registration;
}

/**
 * Les autres listeners de RegistrationCreated (LinkRegistrationToContact,
 * SendConfirmationEmail...) supposent CurrentOrganization déjà positionné
 * par ResolveGuestEvent, comme dans le vrai parcours invité — à remettre
 * explicitement avant de déclencher l'événement à la main, faute de quoi
 * ils lèvent MissingOrganizationContextException.
 */
function fireRegistrationCreated(Organization $organization, Registration $registration): void
{
    app(CurrentOrganization::class)->set($organization);
    event(new RegistrationCreated($registration));
    app(CurrentOrganization::class)->clear();
}

it('notifie un organisateur qui a activé les notifications pour cet événement', function (): void {
    Mail::fake();
    ['organization' => $organization, 'event' => $event] = makeCheckInEvent(MembershipRole::Owner);
    $registration = createRegistrationFor($organization, $event);

    fireRegistrationCreated($organization, $registration);

    Mail::assertQueued(OrganizerRegistrationNotificationMail::class, fn () => true);
});

it('n\'envoie rien quand le réglage général est désactivé', function (): void {
    Mail::fake();
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    $owner->update(['registration_notifications_enabled' => false]);
    $registration = createRegistrationFor($organization, $event);

    fireRegistrationCreated($organization, $registration);

    Mail::assertNothingQueued();
});

it('n\'envoie rien pour cet événement quand la préférence spécifique est désactivée', function (): void {
    Mail::fake();
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    app(CurrentOrganization::class)->set($organization);
    app(SetRegistrationNotificationPreference::class)->handle($owner, $event, false, true, true);
    app(CurrentOrganization::class)->clear();

    $registration = createRegistrationFor($organization, $event);

    fireRegistrationCreated($organization, $registration);

    Mail::assertNothingQueued();
});
