<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventChecklistMark;
use App\Domain\Event\Models\EventChecklistStep;
use App\Domain\Organization\Models\CollaboratorPermission;
use App\Domain\Organization\Models\MembershipRole;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;

/**
 * Parcourt les onglets de la liste de contrôle et renvoie l'étape demandée.
 *
 * @param  array<string, mixed>  $checklist
 * @return array<string, mixed>
 */
function checklistStep(array $checklist, EventChecklistStep $step): array
{
    foreach ($checklist['tabs'] as $tab) {
        foreach ($tab['steps'] as $candidate) {
            if ($candidate['key'] === $step->value) {
                return $candidate;
            }
        }
    }

    throw new RuntimeException("Étape {$step->value} absente de la liste de contrôle.");
}

it('redirige vers la liste de contrôle juste après la création de l\'événement', function (): void {
    ['organization' => $organization, 'doorStaff' => $admin] = makeCheckInEvent(MembershipRole::Admin);

    $response = $this->actingAs($admin)->post('/events', [
        'title' => 'Conférence Itaza 2027',
        'start_at' => '2027-03-10T09:00',
        'timezone' => 'Africa/Kinshasa',
    ]);

    app(CurrentOrganization::class)->set($organization);
    $event = Event::query()->where('title', 'Conférence Itaza 2027')->sole();
    $response->assertRedirect(route('events.show', $event));
});

it('affiche les 14 étapes réparties en quatre onglets, aucune complète pour un brouillon vide', function (): void {
    ['organization' => $organization, 'doorStaff' => $admin] = makeCheckInEvent(MembershipRole::Admin);
    app(CurrentOrganization::class)->set($organization);
    $event = Event::factory()->for($organization)->create();
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($admin)->get("/events/{$event->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Events/Checklist')
        ->where('checklist.total', 14)
        ->where('checklist.completed', 0)
        ->where('checklist.percent', 0)
        ->has('checklist.tabs', 4)
        ->where('checklist.tabs.0.label', 'Personnaliser')
        ->has('checklist.tabs.0.steps', 4)
        ->where('canUpdate', true));
});

it('complète automatiquement les étapes qu\'Itaza constate dans les données', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    $owner = User::factory()->create();
    $owner->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Owner]);

    $response = $this->actingAs($owner)->get("/events/{$event->id}");

    $response->assertInertia(function ($page): void {
        $checklist = $page->toArray()['props']['checklist'];

        expect($checklist['completed'])->toBe(2);
        expect(checklistStep($checklist, EventChecklistStep::Form))->toMatchArray(['done' => true, 'automatic' => true, 'markable' => false]);
        expect(checklistStep($checklist, EventChecklistStep::Publish))->toMatchArray(['done' => true, 'automatic' => true]);
        expect(checklistStep($checklist, EventChecklistStep::Preview))->toMatchArray(['done' => false, 'markable' => true]);
    });
});

it('coche puis décoche une étape à la main sans supprimer de ligne', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $admin] = makeCheckInEvent(MembershipRole::Admin);

    $this->actingAs($admin)->post("/events/{$event->id}/checklist", ['step' => 'apercu', 'completed' => true])->assertRedirect();
    $this->actingAs($admin)->get("/events/{$event->id}")->assertInertia(fn ($page) => $page->where('checklist.completed', 2));

    $this->actingAs($admin)->post("/events/{$event->id}/checklist", ['step' => 'apercu', 'completed' => false])->assertRedirect();

    app(CurrentOrganization::class)->set($organization);
    $mark = EventChecklistMark::query()->where('event_id', $event->id)->sole();
    expect($mark->marked_at)->toBeNull();
});

it('refuse de cocher la publication à la main', function (): void {
    ['event' => $event, 'doorStaff' => $admin] = makeCheckInEvent(MembershipRole::Admin);

    $response = $this->actingAs($admin)->post("/events/{$event->id}/checklist", ['step' => 'publication', 'completed' => true]);

    $response->assertSessionHasErrors('step');
});

it('refuse de cocher une étape à un rôle sans updateEvents', function (): void {
    ['event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent(MembershipRole::DoorStaff);

    $this->actingAs($doorStaff)->post("/events/{$event->id}/checklist", ['step' => 'apercu', 'completed' => true])->assertForbidden();
});

it('partage la navigation de l\'événement avec toutes ses pages', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    app(CurrentOrganization::class)->set($organization);
    $draft = Event::factory()->for($organization)->create(['title' => 'Brouillon partagé']);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($owner)->get("/events/{$draft->id}/dashboard");

    $response->assertInertia(fn ($page) => $page
        ->where('eventNav.id', $draft->id)
        ->where('eventNav.title', 'Brouillon partagé')
        ->where('eventNav.status', 'draft')
        ->where('eventNav.publicUrl', null)
        ->where('eventNav.previewUrl', fn (?string $url): bool => is_string($url) && str_contains($url, 'signature='))
        ->where('eventNav.links.checklist', route('events.show', $draft->id))
        ->where('eventNav.links.form', route('forms.index', $draft->id))
        ->where('eventNav.canChangeStatus', true));
});

it('masque au collaborateur en lecture seule les liens qu\'il ne peut pas ouvrir', function (): void {
    ['organization' => $organization, 'event' => $event] = makeCheckInEvent(MembershipRole::Owner);
    $collaborator = makeEventCollaborator($organization, $event, CollaboratorPermission::CheckIn);

    $response = $this->actingAs($collaborator)->get("/events/{$event->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('canUpdate', false)
        ->where('eventNav.canChangeStatus', false)
        ->where('eventNav.previewUrl', null)
        ->where('eventNav.links.checkIn', route('events.check-in.index', $event->id))
        ->where('eventNav.links.settings', null)
        ->where('eventNav.links.import', null)
        ->where('eventNav.links.collaborators', null));
});

it('ne partage aucune navigation d\'événement hors des pages d\'un événement', function (): void {
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $this->actingAs($owner)->get('/dashboard')->assertInertia(fn ($page) => $page->where('eventNav', null));
});
