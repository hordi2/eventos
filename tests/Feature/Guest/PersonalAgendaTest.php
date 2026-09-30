<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\Speaker;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Organization\Models\Organization;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Registration\PersonalAgendaLink;
use Tests\TestCase;

/**
 * Inscription complète d'une invitée qui retient le dîner de gala, sur
 * l'événement à deux sessions de SubEventFlowTest.
 *
 * @return array{organization: Organization, event: Event, dinner: Event, registration: Registration, agenda: string}
 */
function registrationWithSessions(TestCase $test, array $sessionIds = []): array
{
    ['organization' => $organization, 'event' => $event, 'dinner' => $dinner, 'base' => $base] = guestEventWithSessions();

    $test->get("{$base}/commencer");
    $token = sessionDraftToken($event);
    $test->post("{$base}/{$token}/identite", ['email' => 'awa@example.com', 'first_name' => 'Awa', 'last_name' => 'Diallo']);
    $test->post("{$base}/{$token}/reponses", ['sessions' => $sessionIds === [] ? [$dinner->id] : $sessionIds])->assertSessionHasNoErrors();
    $test->post("{$base}/{$token}/recap");

    app(CurrentOrganization::class)->set($organization);
    $registration = Registration::query()->whereNull('parent_registration_id')->where('event_id', $event->id)->sole();
    $agenda = app(PersonalAgendaLink::class)->url($event, $registration);
    app(CurrentOrganization::class)->clear();

    return ['organization' => $organization, 'event' => $event, 'dinner' => $dinner, 'registration' => $registration, 'agenda' => $agenda];
}

it('montre le programme des sessions retenues, avec salle et intervenants', function (): void {
    ['organization' => $organization, 'dinner' => $dinner, 'agenda' => $agenda] = registrationWithSessions($this);

    app(CurrentOrganization::class)->set($organization);
    $dinner->update(['room' => 'Grand amphithéâtre']);
    $speaker = Speaker::factory()->create(['organization_id' => $organization->id, 'event_id' => $dinner->parent_event_id, 'name' => 'Moussa Kabila']);
    $speaker->sessions()->attach($dinner->id, ['organization_id' => $organization->id]);
    app(CurrentOrganization::class)->clear();

    $this->get($agenda)
        ->assertOk()
        ->assertSee('Mon agenda')
        ->assertSee('Awa Diallo')
        ->assertSee('Dîner de gala')
        ->assertSee('Grand amphithéâtre')
        ->assertSee('Moussa Kabila')
        ->assertDontSee('Cocktail');
});

it('livre le programme personnel en fichier d\'agenda', function (): void {
    ['agenda' => $agenda, 'event' => $event, 'organization' => $organization] = registrationWithSessions($this);

    app(CurrentOrganization::class)->set($organization);
    $ics = app(PersonalAgendaLink::class)->icsUrl($event, Registration::query()->whereNull('parent_registration_id')->sole());
    app(CurrentOrganization::class)->clear();

    $response = $this->get($ics)->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/calendar');

    $content = $response->getContent();
    // L'événement lui-même, puis chaque session retenue.
    expect(substr_count($content, 'BEGIN:VEVENT'))->toBe(2)
        ->and($content)->toContain('Dîner de gala')
        ->and($content)->not->toContain('Cocktail');
});

it('refuse un lien dont la signature a été touchée', function (): void {
    ['agenda' => $agenda] = registrationWithSessions($this);

    $this->get($agenda.'x')->assertForbidden();
});

it('ferme le programme d\'une inscription annulée', function (): void {
    ['organization' => $organization, 'registration' => $registration, 'agenda' => $agenda] = registrationWithSessions($this);

    app(CurrentOrganization::class)->set($organization);
    $registration->update(['status' => RegistrationStatus::Cancelled]);
    app(CurrentOrganization::class)->clear();

    $this->get($agenda)->assertGone();
});

it('propose le programme depuis la confirmation, et seulement avec des sessions', function (): void {
    ['organization' => $organization, 'event' => $event, 'dinner' => $dinner] = guestEventWithSessions();
    $base = "/r/{$organization->slug}/{$event->slug}";

    // Sans session retenue : pas de programme à montrer.
    $this->get("{$base}/commencer");
    $token = sessionDraftToken($event);
    $this->post("{$base}/{$token}/identite", ['email' => 'sans@example.com', 'first_name' => 'Sans']);
    $this->post("{$base}/{$token}/reponses", ['sessions' => []]);
    $this->post("{$base}/{$token}/recap");
    $this->get("{$base}/{$token}/confirmation")->assertOk()->assertDontSee('Voir mon programme');

    // Avec une session : le bouton apparaît.
    $this->get("{$base}/commencer");
    $second = sessionDraftToken($event);
    $this->post("{$base}/{$second}/identite", ['email' => 'avec@example.com', 'first_name' => 'Avec']);
    $this->post("{$base}/{$second}/reponses", ['sessions' => [$dinner->id]]);
    $this->post("{$base}/{$second}/recap");
    $this->get("{$base}/{$second}/confirmation")->assertOk()->assertSee('Voir mon programme');
});

it('dit ce qu\'il en est quand la session retenue est en liste d\'attente', function (): void {
    ['organization' => $organization, 'agenda' => $agenda] = registrationWithSessions($this);

    app(CurrentOrganization::class)->set($organization);
    Registration::query()->whereNotNull('parent_registration_id')->sole()->update(['status' => RegistrationStatus::Waitlisted]);
    app(CurrentOrganization::class)->clear();

    $this->get($agenda)->assertOk()->assertSee("liste d'attente de cette session");
});
