<?php

declare(strict_types=1);

use App\Domain\Event\Models\EventType;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Organization\Models\MembershipRole;
use App\Support\MultiTenancy\CurrentOrganization;

it('propose d\'ajouter l\'événement à son agenda, Google ou fichier', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    $base = "/r/{$organization->slug}/{$event->slug}";

    $this->get($base)
        ->assertOk()
        ->assertSee('Ajouter à Google Agenda')
        ->assertSee('calendar.google.com/calendar/render', false);

    $ics = $this->get("{$base}/agenda.ics")->assertOk();
    expect($ics->headers->get('Content-Type'))->toContain('text/calendar');
    expect($ics->getContent())->toContain('BEGIN:VEVENT')->toContain($event->title);
});

it('propose aussi l\'agenda sur la confirmation d\'inscription', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent([], ['type' => EventType::Conference]);
    $base = "/r/{$organization->slug}/{$event->slug}";

    $this->get("{$base}/commencer");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->sole()->resume_token;
    $this->post("{$base}/{$token}/identite", ['email' => 'awa@example.com', 'first_name' => 'Awa']);
    $this->post("{$base}/{$token}/reponses", []);
    $this->post("{$base}/{$token}/recap");

    $this->get("{$base}/{$token}/confirmation")->assertOk()->assertSee('Ajouter à Google Agenda');
});

it('ne charge la mesure d\'audience qu\'après l\'accord de l\'invité', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();

    // Sans identifiant, aucun bandeau, aucun script.
    $this->get("/r/{$organization->slug}/{$event->slug}")
        ->assertOk()
        ->assertDontSee('googletagmanager.com', false);

    app(CurrentOrganization::class)->set($organization);
    $organization->update(['ga4_measurement_id' => 'G-ABC12345']);
    app(CurrentOrganization::class)->clear();

    // Le bandeau demande l'accord ; le script n'est posé qu'ensuite, par le navigateur.
    $response = $this->get("/r/{$organization->slug}/{$event->slug}")
        ->assertOk()
        ->assertSee("Acceptez-vous la mesure d'audience")
        ->assertSee('G-ABC12345', false);

    expect($response->getContent())->not->toContain('<script async src="https://www.googletagmanager.com');
});

it('enregistre l\'identifiant de mesure et refuse un identifiant mal formé', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Owner);

    $this->actingAs($admin)->post('/settings/api/analytics', ['ga4_measurement_id' => 'g-abc12345'])
        ->assertSessionHas('status', 'analytics-saved');

    expect($organization->fresh()->ga4_measurement_id)->toBe('G-ABC12345');

    $this->actingAs($admin)->post('/settings/api/analytics', ['ga4_measurement_id' => 'UA-12345'])
        ->assertSessionHasErrors('ga4_measurement_id');

    // Vide : la mesure est retirée.
    $this->actingAs($admin)->post('/settings/api/analytics', ['ga4_measurement_id' => '']);
    expect($organization->fresh()->ga4_measurement_id)->toBeNull();
});

it('ne charge le pixel Meta qu\'après l\'accord de l\'invité', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();

    $this->get("/r/{$organization->slug}/{$event->slug}")
        ->assertOk()
        ->assertDontSee('connect.facebook.net', false);

    app(CurrentOrganization::class)->set($organization);
    $organization->update(['meta_pixel_id' => '123456789012345']);
    app(CurrentOrganization::class)->clear();

    // Pixel seul, sans GA4 : le bandeau demande quand même l'accord, et
    // aucun script Meta n'est posé par le serveur.
    $response = $this->get("/r/{$organization->slug}/{$event->slug}")
        ->assertOk()
        ->assertSee("Acceptez-vous la mesure d'audience")
        ->assertSee('123456789012345', false)
        ->assertDontSee('googletagmanager.com', false);

    expect($response->getContent())->not->toContain('<script async src="https://connect.facebook.net');
    expect($response->headers->get('Content-Security-Policy'))->toContain('https://connect.facebook.net');
});

it('enregistre l\'identifiant du pixel Meta et refuse un identifiant mal formé', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Owner);

    $this->actingAs($admin)->post('/settings/api/analytics', ['ga4_measurement_id' => '', 'meta_pixel_id' => '1234 5678 9012 345'])
        ->assertSessionHas('status', 'analytics-saved');

    expect($organization->fresh()->meta_pixel_id)->toBe('123456789012345');

    $this->actingAs($admin)->post('/settings/api/analytics', ['ga4_measurement_id' => '', 'meta_pixel_id' => 'fb-123'])
        ->assertSessionHasErrors('meta_pixel_id');

    // Vide : le pixel est retiré.
    $this->actingAs($admin)->post('/settings/api/analytics', ['ga4_measurement_id' => '', 'meta_pixel_id' => '']);
    expect($organization->fresh()->meta_pixel_id)->toBeNull();
});
