<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventStatus;
use App\Domain\Form\Actions\CreateForm;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\Events\EventPublicLinks;
use App\Support\MultiTenancy\CurrentOrganization;

/**
 * @return array{organization: Organization, event: Event, owner: User}
 */
function makePublishableEvent(EventStatus $status): array
{
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();

    // fresh() passe par le scope organisation : à relire avant de retirer le contexte.
    app(CurrentOrganization::class)->set($organization);
    $event->update(['status' => $status]);
    $event = $event->fresh();
    $owner = User::factory()->create();
    $owner->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Owner]);
    app(CurrentOrganization::class)->clear();

    return ['organization' => $organization, 'event' => $event, 'owner' => $owner];
}

it('publie un événement depuis le menu de statut', function (): void {
    ['organization' => $organization, 'event' => $event, 'owner' => $owner] = makePublishableEvent(EventStatus::Draft);

    $response = $this->actingAs($owner)->post("/events/{$event->id}/publish");

    $response->assertRedirect();
    $response->assertSessionHas('status', 'event-published');
    app(CurrentOrganization::class)->set($organization);
    expect($event->fresh()->status)->toBe(EventStatus::Published);
});

it('refuse de publier un événement sans formulaire publié, avec un message clair', function (): void {
    [$organization, $owner] = organizationWithContactRole(MembershipRole::Owner);
    $event = Event::factory()->for($organization)->create();

    $this->actingAs($owner)->post("/events/{$event->id}/publish")
        ->assertSessionHasErrors(['status' => "Créez et publiez d'abord le formulaire d'inscription : sans lui, vos invités ne pourraient pas répondre."]);

    // Un formulaire encore en brouillon ne suffit pas : c'est lui que le lien ouvrirait.
    app(CurrentOrganization::class)->set($organization);
    app(CreateForm::class)->handle($organization, $event->id, $owner, ['name' => 'Inscription au gala', 'fields' => []]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($owner)->post("/events/{$event->id}/publish")
        ->assertSessionHasErrors(['status' => "Publiez d'abord le formulaire « Inscription au gala » : c'est lui qu'ouvre le lien de l'événement."]);

    app(CurrentOrganization::class)->set($organization);
    expect($event->fresh()->status)->toBe(EventStatus::Draft);
});

it('dépublie un événement : sa page publique redevient introuvable', function (): void {
    ['organization' => $organization, 'event' => $event, 'owner' => $owner] = makePublishableEvent(EventStatus::Published);
    $publicPath = "/r/{$organization->slug}/{$event->slug}";

    $this->get($publicPath)->assertOk();

    $response = $this->actingAs($owner)->post("/events/{$event->id}/unpublish");

    $response->assertSessionHas('status', 'event-unpublished');
    app(CurrentOrganization::class)->set($organization);
    expect($event->fresh()->status)->toBe(EventStatus::Draft);
    app(CurrentOrganization::class)->clear();

    auth()->logout();
    $this->flushSession();
    $this->get($publicPath)->assertNotFound();
});

it('refuse de dépublier un brouillon, avec un message', function (): void {
    ['event' => $event, 'owner' => $owner] = makePublishableEvent(EventStatus::Draft);

    $this->actingAs($owner)->post("/events/{$event->id}/unpublish")->assertSessionHasErrors('status');
});

it('refuse de changer le statut à un rôle sans updateEvents', function (): void {
    ['event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent(MembershipRole::DoorStaff);

    $this->actingAs($doorStaff)->post("/events/{$event->id}/unpublish")->assertForbidden();
});

it('ouvre la page publique d\'un brouillon avec le lien d\'aperçu signé, et seulement avec lui', function (): void {
    ['organization' => $organization, 'event' => $event] = makePublishableEvent(EventStatus::Draft);

    $this->get("/r/{$organization->slug}/{$event->slug}")->assertNotFound();

    app(CurrentOrganization::class)->set($organization);
    $previewUrl = app(EventPublicLinks::class)->previewUrl($event->load('organization'));
    app(CurrentOrganization::class)->clear();

    $this->get($previewUrl)->assertOk();
});

it('refuse un lien d\'aperçu dont la signature a été modifiée', function (): void {
    ['organization' => $organization, 'event' => $event] = makePublishableEvent(EventStatus::Draft);

    app(CurrentOrganization::class)->set($organization);
    $previewUrl = app(EventPublicLinks::class)->previewUrl($event->load('organization'));
    app(CurrentOrganization::class)->clear();

    $this->get($previewUrl.'0')->assertNotFound();
});

it('garde fermé un événement archivé, même avec un lien d\'aperçu', function (): void {
    ['organization' => $organization, 'event' => $event] = makePublishableEvent(EventStatus::Archived);

    app(CurrentOrganization::class)->set($organization);
    $previewUrl = app(EventPublicLinks::class)->previewUrl($event->load('organization'));
    app(CurrentOrganization::class)->clear();

    $this->get($previewUrl)->assertNotFound();
});
