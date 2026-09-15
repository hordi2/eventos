<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * @return array{0: Organization, 1: User, 2: Event}
 */
function parentEventForSessions(MembershipRole $role = MembershipRole::Admin): array
{
    [$organization, $user] = organizationWithContactRole($role);
    $parent = Event::factory()->for($organization)->create(['timezone' => 'Africa/Kinshasa']);

    return [$organization, $user, $parent];
}

it('affiche les sessions d\'un événement avec leurs horaires locaux et leurs conflits', function (): void {
    [$organization, $admin, $parent] = parentEventForSessions();
    $start = CarbonImmutable::parse('2026-12-10 17:00', 'UTC');
    Event::factory()->for($organization)->create(['parent_event_id' => $parent->id, 'title' => 'Dîner', 'start_at' => $start, 'end_at' => $start->addHours(3), 'capacity' => 40, 'timezone' => 'Africa/Kinshasa']);
    Event::factory()->for($organization)->create(['parent_event_id' => $parent->id, 'title' => 'Cocktail', 'start_at' => $start->addHour(), 'end_at' => $start->addHours(2), 'timezone' => 'Africa/Kinshasa']);

    $this->actingAs($admin)->get("/events/{$parent->id}/sub-events")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/SubEvents')
            ->has('subEvents', 2)
            ->where('subEvents.0.title', 'Dîner')
            ->where('subEvents.0.startAt', '2026-12-10T18:00')
            ->where('subEvents.0.capacity', 40)
            ->where('subEvents.0.conflicts', ['Cocktail'])
            ->where('eventNav.links.subEvents', route('events.sub-events.index', $parent->id)));
});

it('crée une session dans le fuseau de l\'événement principal', function (): void {
    [, $admin, $parent] = parentEventForSessions();

    $this->actingAs($admin)->post("/events/{$parent->id}/sub-events", [
        'title' => 'Atelier photo',
        'start_at' => '2026-12-11T10:00',
        'end_at' => '2026-12-11T12:00',
        'capacity' => 20,
        'allow_waitlist' => true,
    ])->assertRedirect(route('events.sub-events.index', $parent->id));

    $session = Event::query()->where('parent_event_id', $parent->id)->firstOrFail();
    expect($session->title)->toBe('Atelier photo');
    expect($session->timezone)->toBe('Africa/Kinshasa');
    expect($session->start_at->utc()->format('Y-m-d H:i'))->toBe('2026-12-11 09:00');
    expect([$session->capacity, $session->allow_waitlist])->toBe([20, true]);
});

it('refuse une session qui finit avant de commencer', function (): void {
    [, $admin, $parent] = parentEventForSessions();

    $this->actingAs($admin)->post("/events/{$parent->id}/sub-events", [
        'title' => 'Atelier',
        'start_at' => '2026-12-11T10:00',
        'end_at' => '2026-12-11T09:00',
    ])->assertSessionHasErrors('end_at');
});

it('modifie une session', function (): void {
    [$organization, $admin, $parent] = parentEventForSessions();
    $session = Event::factory()->for($organization)->create(['parent_event_id' => $parent->id, 'timezone' => 'Africa/Kinshasa', 'capacity' => 30]);

    $this->actingAs($admin)->patch("/events/{$parent->id}/sub-events/{$session->id}", [
        'title' => 'Dîner assis',
        'start_at' => '2026-12-10T19:00',
        'end_at' => '2026-12-10T22:00',
        'capacity' => null,
        'allow_waitlist' => false,
    ])->assertRedirect(route('events.sub-events.index', $parent->id));

    expect($session->fresh()->title)->toBe('Dîner assis');
    expect($session->fresh()->capacity)->toBeNull();
});

it('refuse de supprimer une session qui a des inscrits, puis supprime une session vide', function (): void {
    [$organization, $admin, $parent] = parentEventForSessions();
    $session = Event::factory()->for($organization)->create(['parent_event_id' => $parent->id]);
    makeCheckedInAttendee($organization, $session);

    $this->actingAs($admin)->delete("/events/{$parent->id}/sub-events/{$session->id}")
        ->assertRedirect(route('events.sub-events.index', $parent->id))
        ->assertSessionHasErrors('subEvent');
    expect(Event::query()->find($session->id))->not->toBeNull();

    $empty = Event::factory()->for($organization)->create(['parent_event_id' => $parent->id]);

    $this->actingAs($admin)->delete("/events/{$parent->id}/sub-events/{$empty->id}")->assertSessionHasNoErrors();
    expect(Event::query()->find($empty->id))->toBeNull();
});

it('refuse la gestion des sessions à un membre en lecture seule', function (): void {
    [, $viewer, $parent] = parentEventForSessions(MembershipRole::Viewer);

    $this->actingAs($viewer)->get("/events/{$parent->id}/sub-events")->assertForbidden();
});

it('ne crée jamais de session à l\'intérieur d\'une session', function (): void {
    [$organization, $admin, $parent] = parentEventForSessions();
    $session = Event::factory()->for($organization)->create(['parent_event_id' => $parent->id]);

    $this->actingAs($admin)->get("/events/{$session->id}/sub-events")->assertNotFound();
});
