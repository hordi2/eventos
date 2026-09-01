<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Support\MultiTenancy\CurrentOrganization;

it('n\'expose que les événements de l\'organisation courante', function (): void {
    $organizationA = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organizationA);
    $eventA = Event::factory()->for($organizationA)->create();

    $organizationB = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organizationB);
    $eventB = Event::factory()->for($organizationB)->create();

    $ids = Event::query()->pluck('id');

    expect($ids)->toContain($eventB->id);
    expect($ids)->not->toContain($eventA->id);
});

it('bloque au niveau base la lecture d\'un événement d\'une autre organisation par son id', function (): void {
    $organizationA = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organizationA);
    $eventA = Event::factory()->for($organizationA)->create();

    $organizationB = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organizationB);

    expect(Event::query()->find($eventA->id))->toBeNull();
});
