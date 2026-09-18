<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\MembershipRole;

function registrationWindowPayload(Event $event, array $window): array
{
    return array_merge([
        'title' => $event->title,
        'start_at' => '2026-12-12T18:00',
        'end_at' => '2026-12-12T23:00',
        'timezone' => 'Africa/Kinshasa',
    ], $window);
}

it('règle l\'ouverture et la fermeture à l\'heure de l\'événement et les stocke en UTC', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create(['timezone' => 'Africa/Kinshasa']);

    $this->actingAs($admin)->patch("/events/{$event->id}", registrationWindowPayload($event, [
        'registration_opens_at' => '2026-11-01T09:00',
        'registration_closes_at' => '2026-12-10T20:00',
    ]))->assertRedirect(route('events.edit', $event));

    $fresh = $event->fresh();
    // Kinshasa est à UTC+1 : 9 h locales = 8 h UTC (règle 4.3 du CLAUDE.md).
    expect($fresh->registration_opens_at->toIso8601String())->toBe('2026-11-01T08:00:00+00:00');
    expect($fresh->registration_closes_at->toIso8601String())->toBe('2026-12-10T19:00:00+00:00');

    $this->actingAs($admin)->get("/events/{$event->id}/edit")->assertInertia(fn ($page) => $page
        ->where('event.registrationOpensAt', '2026-11-01T08:00:00+00:00')
        ->where('event.registrationClosesAt', '2026-12-10T19:00:00+00:00'));
});

it('refuse une fermeture qui ne vient pas après l\'ouverture', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create(['timezone' => 'Africa/Kinshasa']);

    $this->actingAs($admin)->from("/events/{$event->id}/edit")->patch("/events/{$event->id}", registrationWindowPayload($event, [
        'registration_opens_at' => '2026-11-01T09:00',
        'registration_closes_at' => '2026-11-01T09:00',
    ]))->assertSessionHasErrors('registration_closes_at');

    expect($event->fresh()->registration_opens_at)->toBeNull();
});

it('rouvre les inscriptions quand on efface les dates', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create([
        'timezone' => 'Africa/Kinshasa',
        'registration_opens_at' => now()->addWeek(),
        'registration_closes_at' => now()->addMonth(),
    ]);

    $this->actingAs($admin)->patch("/events/{$event->id}", registrationWindowPayload($event, [
        'registration_opens_at' => '',
        'registration_closes_at' => '',
    ]))->assertRedirect(route('events.edit', $event));

    $fresh = $event->fresh();
    expect($fresh->registration_opens_at)->toBeNull();
    expect($fresh->registration_closes_at)->toBeNull();
});

it('garde les dates d\'ouverture à la création, à l\'heure de l\'événement', function (): void {
    [, $admin] = organizationWithContactRole(MembershipRole::Admin);

    $this->actingAs($admin)->post('/events', [
        'title' => 'Gala des partenaires',
        'start_at' => '2026-12-12T18:00',
        'timezone' => 'Africa/Abidjan',
        'registration_opens_at' => '2026-11-01T09:00',
    ]);

    // Abidjan est à UTC+0 : l'heure saisie est déjà l'heure UTC.
    expect(Event::query()->where('title', 'Gala des partenaires')->sole()->registration_opens_at->toIso8601String())
        ->toBe('2026-11-01T09:00:00+00:00');
});
