<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventType;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Networking\ExchangeBadgeContact;
use App\Support\Networking\GetAttendeeDirectory;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Annuaire des participants (D8) : chacun y figure s'il le demande, et rien
 * d'autre que son nom et sa ligne de présentation n'en sort.
 *
 * @return array{organization: object, event: Event, registration: Registration, url: string}
 */
function directoryEvent(bool $open = true): array
{
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent(
        eventOverrides: ['type' => EventType::Conference, 'has_attendee_directory' => $open],
    );

    app(CurrentOrganization::class)->set($organization);
    // La version du formulaire publié par makeGuestReadyEvent : sans elle,
    // la factory en créerait une — avec sa propre organisation, que la
    // row-level security refuserait.
    $version = FormVersion::query()->where('organization_id', $organization->id)->sole();
    $registration = Registration::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'form_version_id' => $version->id,
        'status' => RegistrationStatus::Confirmed,
        'first_name' => 'Awa',
        'last_name' => 'Diallo',
        'email' => 'awa@example.com',
    ]);
    app(CurrentOrganization::class)->clear();

    $url = URL::temporarySignedRoute('guest.registration.directory', now()->addDay(), [
        $organization->slug,
        $event->slug,
        $registration->id,
    ]);

    return ['organization' => $organization, 'event' => $event, 'registration' => $registration, 'url' => $url];
}

it('n\'inscrit personne à l\'annuaire sans son consentement', function (): void {
    ['event' => $event, 'registration' => $registration, 'url' => $url] = directoryEvent();

    $this->get($url)->assertOk()->assertSee('Rejoindre l’annuaire', false);

    app(CurrentOrganization::class)->set($registration->organization_id);
    expect(app(GetAttendeeDirectory::class)->handle($event))->toBe([]);
    app(CurrentOrganization::class)->clear();
});

it('fait figurer le participant qui le demande, avec sa ligne', function (): void {
    ['event' => $event, 'registration' => $registration, 'url' => $url] = directoryEvent();

    $this->post($url, ['join' => 1, 'headline' => 'Médecin, Clinique Ngaliema'])->assertRedirect();

    app(CurrentOrganization::class)->set($registration->organization_id);
    $directory = app(GetAttendeeDirectory::class)->handle($event);

    expect($directory)->toHaveCount(1)
        ->and($directory[0]['name'])->toBe('Awa Diallo')
        ->and($directory[0]['headline'])->toBe('Médecin, Clinique Ngaliema')
        ->and($registration->refresh()->directory_consent_at)->not->toBeNull();
    app(CurrentOrganization::class)->clear();

    // L'annuaire ne publie ni e-mail ni téléphone.
    $html = $this->get($url)->assertOk()->getContent();
    expect($html)->toContain('Awa Diallo')
        ->and($html)->not->toContain('awa@example.com');
});

it('efface le consentement de qui sort de l\'annuaire', function (): void {
    ['event' => $event, 'registration' => $registration, 'url' => $url] = directoryEvent();

    $this->post($url, ['join' => 1, 'headline' => 'Médecin']);
    $this->post($url, ['join' => 0])->assertRedirect();

    app(CurrentOrganization::class)->set($registration->organization_id);
    expect($registration->refresh()->directory_consent_at)->toBeNull()
        // La ligne part avec le consentement : elle n'est pas gardée en réserve.
        ->and($registration->directory_headline)->toBeNull()
        ->and(app(GetAttendeeDirectory::class)->handle($event))->toBe([]);
    app(CurrentOrganization::class)->clear();
});

it('garde l\'annuaire fermé tant que l\'organisateur ne l\'ouvre pas', function (): void {
    ['event' => $event, 'registration' => $registration, 'url' => $url] = directoryEvent(open: false);

    $this->get($url)->assertNotFound();
    $this->post($url, ['join' => 1])->assertNotFound();

    app(CurrentOrganization::class)->set($registration->organization_id);
    expect(app(GetAttendeeDirectory::class)->handle($event))->toBe([]);
    app(CurrentOrganization::class)->clear();
});

it('refuse l\'annuaire sans lien signé', function (): void {
    ['organization' => $organization, 'event' => $event, 'registration' => $registration] = directoryEvent();

    $this->get("/r/{$organization->slug}/{$event->slug}/inscriptions/{$registration->id}/annuaire")
        ->assertForbidden();
});

it('tient l\'annuaire aux seuls inscrits confirmés', function (): void {
    ['organization' => $organization, 'event' => $event, 'registration' => $registration, 'url' => $url] = directoryEvent();

    app(CurrentOrganization::class)->set($organization);
    $registration->update(['status' => RegistrationStatus::Cancelled]);
    app(CurrentOrganization::class)->clear();

    $this->get($url)->assertNotFound();
});

/**
 * Un second participant du même événement, confirmé et inscrit à l'annuaire.
 */
function secondAttendee(object $organization, Event $event, bool $sharesContact): Registration
{
    app(CurrentOrganization::class)->set($organization);
    $version = FormVersion::query()->where('organization_id', $organization->id)->sole();
    $registration = Registration::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'form_version_id' => $version->id,
        'status' => RegistrationStatus::Confirmed,
        'first_name' => 'Jean',
        'last_name' => 'Mbuyi',
        'email' => 'jean@example.com',
        'directory_consent_at' => now(),
        'directory_headline' => 'Architecte',
        'shares_contact' => $sharesContact,
        'networking_token' => (string) Str::uuid(),
    ]);
    app(CurrentOrganization::class)->clear();

    return $registration;
}

it('enregistre la rencontre quand un participant scanne le badge d\'un autre', function (): void {
    ['organization' => $organization, 'event' => $event, 'registration' => $me, 'url' => $url] = directoryEvent();
    $other = secondAttendee($organization, $event, sharesContact: true);

    // J'ouvre mon lien : c'est lui qui dit qui scanne.
    $this->post($url, ['join' => 1]);
    $this->get($url)->assertOk();

    $this->get("/r/{$organization->slug}/{$event->slug}/badge/{$other->networking_token}")
        ->assertRedirect()
        ->assertSessionHas('status', 'badge-met');

    app(CurrentOrganization::class)->set($organization);
    $connections = app(ExchangeBadgeContact::class)->connectionsOf($me->refresh());

    expect($connections)->toHaveCount(1)
        ->and($connections[0]['name'])->toBe('Jean Mbuyi')
        ->and($connections[0]['email'])->toBe('jean@example.com')
        ->and($connections[0]['scannedByMe'])->toBeTrue();

    // La rencontre vaut dans les deux sens.
    $theirs = app(ExchangeBadgeContact::class)->connectionsOf($other->refresh());
    expect($theirs)->toHaveCount(1)
        ->and($theirs[0]['scannedByMe'])->toBeFalse();
    app(CurrentOrganization::class)->clear();
});

it('ne compte qu\'une rencontre, même scannée dix fois', function (): void {
    ['organization' => $organization, 'event' => $event, 'registration' => $me, 'url' => $url] = directoryEvent();
    $other = secondAttendee($organization, $event, sharesContact: false);

    $this->post($url, ['join' => 1]);
    $this->get($url);

    foreach (range(1, 3) as $ignored) {
        $this->get("/r/{$organization->slug}/{$event->slug}/badge/{$other->networking_token}");
    }

    app(CurrentOrganization::class)->set($organization);
    $connections = app(ExchangeBadgeContact::class)->connectionsOf($me->refresh());

    expect($connections)->toHaveCount(1)
        // Sans son accord, l'adresse de l'autre ne sort pas.
        ->and($connections[0]['email'])->toBeNull();
    app(CurrentOrganization::class)->clear();
});

it('ne sait pas qui scanne tant que le participant n\'a pas ouvert son lien', function (): void {
    ['organization' => $organization, 'event' => $event] = directoryEvent();
    $other = secondAttendee($organization, $event, sharesContact: true);

    $this->get("/r/{$organization->slug}/{$event->slug}/badge/{$other->networking_token}")
        ->assertOk()
        ->assertSee('On ne sait pas encore qui vous êtes', false);
});

it('rend le badge inutilisable quand on sort de l\'annuaire', function (): void {
    ['organization' => $organization, 'event' => $event, 'registration' => $me, 'url' => $url] = directoryEvent();

    $this->post($url, ['join' => 1]);
    $this->get($url)->assertOk();

    app(CurrentOrganization::class)->set($organization);
    $token = $me->refresh()->networking_token;
    expect($token)->not->toBeNull();
    app(CurrentOrganization::class)->clear();

    $this->post($url, ['join' => 0]);

    app(CurrentOrganization::class)->set($organization);
    expect($me->refresh()->networking_token)->toBeNull()
        ->and($me->shares_contact)->toBeFalse();
    app(CurrentOrganization::class)->clear();
});
