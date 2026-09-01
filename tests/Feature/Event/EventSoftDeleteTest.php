<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Support\MultiTenancy\CurrentOrganization;

it('supprime un événement en douceur sans effacer la ligne', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $event = Event::factory()->for($organization)->create();

    $event->delete();

    $trashed = Event::withTrashed()->find($event->id);
    expect($trashed)->not->toBeNull();
    expect($trashed->deleted_at)->not->toBeNull();
    expect(Event::query()->find($event->id))->toBeNull();
});

it('restaure un événement supprimé en douceur', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $event = Event::factory()->for($organization)->create();
    $event->delete();

    $event->restore();

    expect(Event::query()->find($event->id))->not->toBeNull();
    expect($event->fresh()->deleted_at)->toBeNull();
});

it('exclut les événements supprimés des requêtes par défaut', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $kept = Event::factory()->for($organization)->create();
    $deleted = Event::factory()->for($organization)->create();
    $deleted->delete();

    $ids = Event::query()->pluck('id');

    expect($ids)->toContain($kept->id);
    expect($ids)->not->toContain($deleted->id);
});
