<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventType;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Networking\GetAttendeeDirectory;
use Illuminate\Support\Facades\URL;

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
