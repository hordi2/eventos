<?php

declare(strict_types=1);

use App\Domain\Event\Models\EventType;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Organization\Models\Organization;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\URL;

/**
 * Application participant en marque blanche (D9).
 *
 * @return array{organization: Organization, event: object, registration: Registration, url: string}
 */
function participantAppEvent(bool $open = true, bool $directory = false): array
{
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent(eventOverrides: [
        'type' => EventType::Conference,
        'has_participant_app' => $open,
        'has_attendee_directory' => $directory,
    ]);

    app(CurrentOrganization::class)->set($organization);
    $version = FormVersion::query()->where('organization_id', $organization->id)->sole();
    $registration = Registration::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'form_version_id' => $version->id,
        'status' => RegistrationStatus::Confirmed,
    ]);
    app(CurrentOrganization::class)->clear();

    $url = URL::temporarySignedRoute('guest.registration.application', now()->addDay(), [
        $organization->slug, $event->slug, $registration->id,
    ]);

    return ['organization' => $organization, 'event' => $event, 'registration' => $registration, 'url' => $url];
}

it('ouvre la page de l\'application, avec son manifeste et sans l\'annuaire fermé', function (): void {
    ['organization' => $organization, 'event' => $event, 'url' => $url] = participantAppEvent();

    $html = $this->get($url)->assertOk()->getContent();

    expect($html)->toContain('rel="manifest"')
        ->and($html)->not->toContain("Ouvrir l'annuaire");

    $this->get(route('guest.registration.application.manifest', [$organization->slug, $event->slug]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('name', $event->title)
        ->assertJsonPath('display', 'standalone');
});

it('propose l\'annuaire depuis l\'application quand il est ouvert, avec un lien qui s\'ouvre vraiment', function (): void {
    ['url' => $url] = participantAppEvent(directory: true);

    $html = $this->get($url)->assertOk()->assertSee("Ouvrir l'annuaire")->getContent();

    preg_match('/href="([^"]+)"[^>]*>\s*Ouvrir l&#039;annuaire/s', $html, $matches);
    $directoryUrl = html_entity_decode($matches[1]);

    expect($directoryUrl)->toContain('signature=');
    $this->get($directoryUrl)->assertOk();
});

it('signe les liens d\'abonnement aux notifications push depuis l\'application', function (): void {
    ['url' => $url] = participantAppEvent();

    $html = $this->get($url)->assertOk()->getContent();

    preg_match('/subscribeUrl:\s*\'([^\']+)\'/', $html, $subscribeMatch);
    preg_match('/unsubscribeUrl:\s*\'([^\']+)\'/', $html, $unsubscribeMatch);
    $decode = fn (string $value): string => json_decode('"'.$value.'"') ?? '';
    $subscribeUrl = $decode($subscribeMatch[1] ?? '');
    $unsubscribeUrl = $decode($unsubscribeMatch[1] ?? '');

    expect($subscribeUrl)->toContain('signature=')
        ->and($unsubscribeUrl)->toContain('signature=');

    $this->postJson($subscribeUrl, ['endpoint' => 'https://fcm.googleapis.com/fcm/send/depuis-la-page'])->assertNoContent();
    $this->deleteJson($unsubscribeUrl, ['endpoint' => 'https://fcm.googleapis.com/fcm/send/depuis-la-page'])->assertNoContent();
});

it('ferme l\'application et son manifeste tant que l\'organisateur ne l\'a pas ouverte', function (): void {
    ['organization' => $organization, 'event' => $event, 'url' => $url] = participantAppEvent(open: false);

    $this->get($url)->assertNotFound();
    $this->get(route('guest.registration.application.manifest', [$organization->slug, $event->slug]))->assertNotFound();
});

it('refuse l\'application sans lien signé', function (): void {
    ['organization' => $organization, 'event' => $event, 'registration' => $registration] = participantAppEvent();

    $this->get("/r/{$organization->slug}/{$event->slug}/inscriptions/{$registration->id}/application")
        ->assertForbidden();
});

it('propose le logo de l\'organisation dans le manifeste, sinon celui d\'Itaza', function (): void {
    ['organization' => $organization, 'event' => $event] = participantAppEvent();

    $manifest = $this->get(route('guest.registration.application.manifest', [$organization->slug, $event->slug]))
        ->assertOk()
        ->json();

    expect($manifest['icons'][0]['src'])->toBe('/images/logo.png');

    $organization->update(['logo_path' => 'organization-logos/1/logo.png']);

    $manifest = $this->get(route('guest.registration.application.manifest', [$organization->slug, $event->slug]))
        ->assertOk()
        ->json();

    expect($manifest['icons'][0]['src'])->toContain('organization-logos/1/logo.png');
});

it('propose l\'application depuis la page de confirmation', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent(eventOverrides: [
        'type' => EventType::Conference,
        'has_participant_app' => true,
    ]);
    $base = "/r/{$organization->slug}/{$event->slug}";

    $this->get("{$base}/commencer");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->latest('id')->firstOrFail()->resume_token;
    $this->get("{$base}/{$token}/accueil");
    $this->post("{$base}/{$token}/identite", ['email' => 'awa@example.com', 'first_name' => 'Awa']);
    $this->post("{$base}/{$token}/reponses", []);
    $this->post("{$base}/{$token}/recap");

    $this->get("{$base}/{$token}/confirmation")->assertOk()->assertSee('Ouvrir mon application');
});
