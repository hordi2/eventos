<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Form;
use App\Domain\Organization\Actions\RemoveCollaborator;
use App\Domain\Organization\Models\Collaborator;
use App\Domain\Organization\Models\CollaboratorPermission;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Laravel\Sanctum\Sanctum;

/**
 * Organisation avec deux événements, dont un seul partagé avec le
 * collaborateur.
 *
 * @return array{organization: Organization, owner: User, sharedEvent: Event, otherEvent: Event, collaborator: User}
 */
function makeSharedEventSetup(CollaboratorPermission $permission): array
{
    ['organization' => $organization, 'event' => $sharedEvent, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    app(CurrentOrganization::class)->set($organization);
    $otherEvent = Event::factory()->for($organization)->published()->create();
    app(CurrentOrganization::class)->clear();

    $collaborator = makeEventCollaborator($organization, $sharedEvent, $permission);

    return [
        'organization' => $organization,
        'owner' => $owner,
        'sharedEvent' => $sharedEvent,
        'otherEvent' => $otherEvent,
        'collaborator' => $collaborator,
    ];
}

it('ouvre le tableau de bord et le check-in de l\'événement partagé en lecture seule + check-in', function (): void {
    ['sharedEvent' => $event, 'collaborator' => $collaborator] = makeSharedEventSetup(CollaboratorPermission::CheckIn);

    $this->actingAs($collaborator)->get("/events/{$event->id}/dashboard")->assertOk();
    $this->actingAs($collaborator)->get("/events/{$event->id}/check-in")->assertOk();
});

it('interdit à la lecture seule de modifier l\'événement partagé', function (): void {
    ['sharedEvent' => $event, 'collaborator' => $collaborator] = makeSharedEventSetup(CollaboratorPermission::CheckIn);

    $this->actingAs($collaborator)->get("/events/{$event->id}/edit")->assertForbidden();
    $this->actingAs($collaborator)->get("/events/{$event->id}/exports")->assertForbidden();
});

it('laisse l\'administrateur de l\'événement le modifier', function (): void {
    ['sharedEvent' => $event, 'collaborator' => $collaborator] = makeSharedEventSetup(CollaboratorPermission::Administrator);

    $this->actingAs($collaborator)->get("/events/{$event->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canDuplicate', false));
    $this->actingAs($collaborator)->get("/events/{$event->id}/exports")->assertOk();
});

it('interdit à l\'administrateur de dupliquer l\'événement dans l\'organisation', function (): void {
    ['sharedEvent' => $event, 'collaborator' => $collaborator] = makeSharedEventSetup(CollaboratorPermission::Administrator);

    $this->actingAs($collaborator)->post("/events/{$event->id}/duplicate", ['title' => 'Copie'])->assertForbidden();
});

it('renvoie 404 sur un événement qui n\'est pas partagé avec le collaborateur', function (): void {
    ['otherEvent' => $otherEvent, 'collaborator' => $collaborator] = makeSharedEventSetup(CollaboratorPermission::Administrator);

    $this->actingAs($collaborator)->get("/events/{$otherEvent->id}/dashboard")->assertNotFound();
    $this->actingAs($collaborator)->get("/events/{$otherEvent->id}/edit")->assertNotFound();
});

it('retrouve l\'événement d\'une ressource enfant avant d\'y donner accès', function (): void {
    ['organization' => $organization, 'otherEvent' => $otherEvent, 'owner' => $owner, 'collaborator' => $collaborator] = makeSharedEventSetup(CollaboratorPermission::Administrator);

    app(CurrentOrganization::class)->set($organization);
    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $otherEvent->id,
        'created_by' => $owner->id,
    ]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($collaborator)->get("/forms/{$form->id}/edit")->assertNotFound();
});

it('refuse au collaborateur les pages de l\'organisation hors de ses événements', function (string $uri): void {
    ['collaborator' => $collaborator] = makeSharedEventSetup(CollaboratorPermission::Administrator);

    $this->actingAs($collaborator)->get($uri)->assertForbidden();
})->with([
    'contacts' => '/contacts',
    'création d\'événement' => '/events/create',
    'modèles d\'e-mail' => '/email-templates',
    'notifications' => '/settings/notifications',
    'intégrations' => '/settings/api',
    'partage d\'événements' => '/settings/event-sharing',
    'facturation' => '/billing',
]);

it('ne liste que les événements partagés dans le tableau de bord et le menu', function (): void {
    ['sharedEvent' => $event, 'collaborator' => $collaborator] = makeSharedEventSetup(CollaboratorPermission::CheckIn);

    $response = $this->actingAs($collaborator)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('events', 1)
        ->where('events.0.id', $event->id)
        ->where('canCreateEvents', false)
        ->has('nav.0.items', 2)
        ->where('nav.0.items.1.href', route('events.show', $event->id))
        ->where('settingsAccess.eventSharing', false));
});

it('coupe l\'accès d\'un collaborateur retiré', function (): void {
    ['organization' => $organization, 'owner' => $owner, 'sharedEvent' => $event, 'collaborator' => $collaborator] = makeSharedEventSetup(CollaboratorPermission::Administrator);

    app(CurrentOrganization::class)->set($organization);
    app(RemoveCollaborator::class)->handle(Collaborator::query()->sole(), $owner);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($collaborator)->get("/events/{$event->id}/dashboard")->assertForbidden();
});

it('garde tous leurs droits aux membres de l\'organisation', function (): void {
    ['owner' => $owner, 'otherEvent' => $otherEvent] = makeSharedEventSetup(CollaboratorPermission::CheckIn);

    $this->actingAs($owner)->get("/events/{$otherEvent->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canDuplicate', true));
    $this->actingAs($owner)->get('/dashboard')->assertInertia(fn ($page) => $page
        ->has('events', 2)
        ->where('settingsAccess.eventSharing', true));
});

it('n\'accorde aucune capacité hors du contexte d\'un événement partagé', function (): void {
    ['organization' => $organization, 'collaborator' => $collaborator] = makeSharedEventSetup(CollaboratorPermission::Administrator);

    app(CurrentOrganization::class)->set($organization);

    expect($collaborator->can('checkIn', $organization))->toBeFalse();
    expect($collaborator->can('updateEvents', $organization))->toBeFalse();
});

it('ouvre l\'API de check-in au collaborateur, pour son seul événement partagé', function (): void {
    ['sharedEvent' => $event, 'otherEvent' => $otherEvent, 'collaborator' => $collaborator] = makeSharedEventSetup(CollaboratorPermission::CheckIn);

    Sanctum::actingAs($collaborator);

    $this->getJson("/api/v1/events/{$event->id}/guests")->assertOk();
    $this->getJson("/api/v1/events/{$otherEvent->id}/guests")->assertNotFound();
});
