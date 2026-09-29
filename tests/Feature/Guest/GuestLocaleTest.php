<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventType;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Organization\Models\MembershipRole;
use App\Support\MultiTenancy\CurrentOrganization;

it('ouvre le parcours dans la langue du navigateur quand nous la parlons', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    $base = "/r/{$organization->slug}/{$event->slug}";

    $this->withHeader('Accept-Language', 'en-GB,en;q=0.9')->get("{$base}/commencer");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->sole()->resume_token;

    $this->withHeader('Accept-Language', 'en-GB,en;q=0.9')->get("{$base}/{$token}/identite")
        ->assertOk()
        ->assertSee('<html lang="en"', false)
        ->assertSee('Your registration')
        ->assertSee('Continue')
        ->assertDontSee('Votre inscription');
});

it('retombe sur la langue de l\'événement quand celle du navigateur nous est étrangère', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();

    $this->withHeader('Accept-Language', 'de-DE,de;q=0.9')->get("/r/{$organization->slug}/{$event->slug}/commencer");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->sole()->resume_token;

    $this->withHeader('Accept-Language', 'de-DE,de;q=0.9')->get("/r/{$organization->slug}/{$event->slug}/{$token}/identite")
        ->assertOk()
        ->assertSee('Votre inscription');

    // Un événement réglé en anglais parle anglais au même navigateur.
    app(CurrentOrganization::class)->set($organization);
    $event->update(['locale' => 'en']);
    app(CurrentOrganization::class)->clear();

    $this->withHeader('Accept-Language', 'de-DE,de;q=0.9')->get("/r/{$organization->slug}/{$event->slug}/{$token}/identite")
        ->assertOk()
        ->assertSee('Your registration');
});

it('retient la langue choisie dans le sélecteur pour la suite du parcours', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    $base = "/r/{$organization->slug}/{$event->slug}";

    $this->get("{$base}/commencer");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->sole()->resume_token;

    // Le sélecteur propose l'autre langue, avec la même page en paramètre.
    $this->get("{$base}/{$token}/identite")->assertSee('lang=en', false)->assertSee('English');

    $this->get("{$base}/{$token}/identite?lang=en")->assertSee('Your registration');
    // Sans paramètre cette fois : le choix est retenu en session.
    $this->get("{$base}/{$token}/reponses")->assertSee('Your answers');

    $this->get("{$base}/{$token}/reponses?lang=fr")->assertSee('Vos réponses');
});

it('affiche les messages de validation en français', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent([], ['type' => EventType::Conference]);
    $base = "/r/{$organization->slug}/{$event->slug}";

    $this->get("{$base}/commencer");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->sole()->resume_token;

    $this->post("{$base}/{$token}/identite", ['email' => 'pas-une-adresse'])
        ->assertSessionHasErrors(['email' => "L'adresse e-mail n'est pas valide."]);
});

it('règle la langue de l\'événement depuis ses paramètres', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create(['timezone' => 'Africa/Kinshasa', 'locale' => 'fr']);

    $this->actingAs($admin)->patch("/events/{$event->id}", [
        'title' => $event->title,
        'start_at' => '2026-12-12T18:00',
        'end_at' => '2026-12-12T23:00',
        'timezone' => 'Africa/Kinshasa',
        'locale' => 'en',
    ])->assertRedirect();

    expect($event->fresh()->locale)->toBe('en');

    $this->actingAs($admin)->patch("/events/{$event->id}", [
        'title' => $event->title,
        'start_at' => '2026-12-12T18:00',
        'end_at' => '2026-12-12T23:00',
        'timezone' => 'Africa/Kinshasa',
        'locale' => 'de',
    ])->assertSessionHasErrors('locale');
});
