<?php

declare(strict_types=1);

use App\Domain\Event\Models\Venue;
use App\Domain\Form\Models\RegistrationDraft;
use App\Support\MultiTenancy\CurrentOrganization;

it('affiche la page événement publique avec le balisage schema.org/Event et le CTA vers le formulaire', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent(eventOverrides: [
        'description' => "Une belle fête.\nÀ ne pas manquer.",
    ]);
    app(CurrentOrganization::class)->set($organization);
    $venue = Venue::factory()->for($organization)->withCoordinates()->create(['name' => 'Salle Fleuve Congo']);
    $event->update(['venue_id' => $venue->id]);
    app(CurrentOrganization::class)->clear();

    $response = $this->get("/r/{$organization->slug}/{$event->slug}");

    $response->assertOk();
    $response->assertSee($event->title);
    $response->assertSee('Salle Fleuve Congo');
    $response->assertSee('"@context":"https://schema.org"', false);
    $response->assertSee('"@type":"Event"', false);
    $response->assertSee("/r/{$organization->slug}/{$event->slug}/commencer", false);
});

it('crée un brouillon d\'inscription seulement en cliquant sur commencer, pas à la simple visite de la page', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();

    $this->get("/r/{$organization->slug}/{$event->slug}")->assertOk();
    expect(RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->count())->toBe(0);

    $this->get("/r/{$organization->slug}/{$event->slug}/commencer")->assertRedirect();
    expect(RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->count())->toBe(1);
});

it('affiche la page fermée quand la fenêtre d\'inscription n\'est pas ouverte', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent([], [
        'registration_opens_at' => now()->addWeek(),
    ]);

    $response = $this->get("/r/{$organization->slug}/{$event->slug}");

    $response->assertOk();
    $response->assertDontSee('schema.org', false);
});
