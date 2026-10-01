<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Domain\Page\Models\GuestBookMessage;
use App\Domain\Page\Models\Page;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;

/**
 * Événement publié dont la page porte un livre d'or.
 *
 * @return array{organization: Organization, event: Event, base: string}
 */
function eventWithGuestBook(): array
{
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();

    app(CurrentOrganization::class)->set($organization);
    Page::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'blocks' => [['id' => 'a', 'type' => 'guest_book', 'title' => null]],
    ]);
    app(CurrentOrganization::class)->clear();

    return ['organization' => $organization, 'event' => $event, 'base' => "/r/{$organization->slug}/{$event->slug}"];
}

it('laisse un invité signer le livre d\'or, et le montre aussitôt', function (): void {
    ['organization' => $organization, 'base' => $base] = eventWithGuestBook();

    $this->get($base)->assertOk()->assertSee("Livre d'or")->assertSee('Laisser mon message');

    $this->post("{$base}/livre-d-or", ['author_name' => 'Awa Diallo', 'message' => 'Tous nos vœux de bonheur.'])
        ->assertSessionHasNoErrors();

    app(CurrentOrganization::class)->set($organization);
    $message = GuestBookMessage::query()->sole();
    expect($message->author_name)->toBe('Awa Diallo')
        ->and($message->is_published)->toBeTrue()
        ->and($message->author_ip)->not->toBeNull();
    app(CurrentOrganization::class)->clear();

    $this->get($base)->assertOk()->assertSee('Tous nos vœux de bonheur.')->assertSee('Awa Diallo');
});

it('refuse un mot sans nom ni message', function (): void {
    ['base' => $base] = eventWithGuestBook();

    $this->post("{$base}/livre-d-or", [])->assertSessionHasErrors(['author_name', 'message']);
});

it('cache le mot que l\'organisateur a masqué', function (): void {
    ['organization' => $organization, 'event' => $event, 'base' => $base] = eventWithGuestBook();

    app(CurrentOrganization::class)->set($organization);
    $shared = ['organization_id' => $organization->id, 'event_id' => $event->id];
    GuestBookMessage::factory()->create([...$shared, 'author_name' => 'Awa', 'message' => 'Un mot visible.']);
    GuestBookMessage::factory()->hidden()->create([...$shared, 'author_name' => 'Spam', 'message' => 'Un mot masqué.']);
    app(CurrentOrganization::class)->clear();

    $this->get($base)->assertOk()->assertSee('Un mot visible.')->assertDontSee('Un mot masqué.');
});

it('laisse l\'organisateur masquer puis retirer un mot', function (): void {
    ['organization' => $organization, 'event' => $event] = eventWithGuestBook();

    app(CurrentOrganization::class)->set($organization);
    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);
    $message = GuestBookMessage::factory()->create([
        'organization_id' => $organization->id, 'event_id' => $event->id,
        'author_name' => 'Awa', 'message' => 'Bravo à vous.',
    ]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->get("/events/{$event->id}/livre-d-or")->assertInertia(fn ($page) => $page
        ->component('Events/GuestBook')
        ->where('messages.0.author', 'Awa')
        ->where('messages.0.isPublished', true));

    $this->actingAs($admin)->patch("/events/{$event->id}/livre-d-or/{$message->id}")->assertSessionHas('status', 'guest-book-updated');

    app(CurrentOrganization::class)->set($organization);
    expect($message->fresh()->is_published)->toBeFalse();
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->delete("/events/{$event->id}/livre-d-or/{$message->id}")->assertSessionHas('status', 'guest-book-removed');

    app(CurrentOrganization::class)->set($organization);
    expect(GuestBookMessage::query()->count())->toBe(0)
        ->and(GuestBookMessage::withTrashed()->count())->toBe(1);
});

it('réserve la modération aux membres qui peuvent modifier l\'événement', function (): void {
    ['organization' => $organization, 'event' => $event] = eventWithGuestBook();

    app(CurrentOrganization::class)->set($organization);
    $viewer = User::factory()->create();
    $viewer->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Viewer]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($viewer)->get("/events/{$event->id}/livre-d-or")->assertForbidden();
});

it('garde le livre d\'or d\'une organisation hors de portée d\'une autre', function (): void {
    ['organization' => $organization, 'event' => $event] = eventWithGuestBook();

    app(CurrentOrganization::class)->set($organization);
    GuestBookMessage::factory()->create(['organization_id' => $organization->id, 'event_id' => $event->id]);
    app(CurrentOrganization::class)->clear();

    [, $intruder] = organizationWithContactRole(MembershipRole::Admin);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($intruder)->get("/events/{$event->id}/livre-d-or")->assertNotFound();
});
