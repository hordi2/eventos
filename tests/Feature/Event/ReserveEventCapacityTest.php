<?php

declare(strict_types=1);

use App\Domain\Event\Actions\ReserveEventCapacity;
use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Support\Capacity\Data\ReservationOutcome;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($this->organization);
});

it('réserve une place sur un événement disposant encore de capacité', function (): void {
    $event = Event::factory()->for($this->organization)->create(['capacity' => 50]);

    $result = app(ReserveEventCapacity::class)->handle($event, (string) Str::uuid());

    expect($result->outcome)->toBe(ReservationOutcome::Accepted);
});

it('bascule sur liste d\'attente quand l\'événement l\'autorise et que la capacité est atteinte', function (): void {
    $event = Event::factory()->for($this->organization)->create(['capacity' => 1, 'allow_waitlist' => true]);
    $action = app(ReserveEventCapacity::class);

    $action->handle($event, (string) Str::uuid());
    $second = $action->handle($event, (string) Str::uuid());

    expect($second->outcome)->toBe(ReservationOutcome::Waitlisted);
    expect($second->waitlistPosition)->toBe(1);
});

it('refuse quand la capacité est atteinte et que la liste d\'attente est désactivée', function (): void {
    $event = Event::factory()->for($this->organization)->create(['capacity' => 1, 'allow_waitlist' => false]);
    $action = app(ReserveEventCapacity::class);

    $action->handle($event, (string) Str::uuid());
    $second = $action->handle($event, (string) Str::uuid());

    expect($second->outcome)->toBe(ReservationOutcome::Rejected);
});

it('gère la capacité d\'un sous-événement indépendamment de l\'événement parent', function (): void {
    $parent = Event::factory()->for($this->organization)->create(['capacity' => 1]);
    $subEvent = Event::factory()->for($this->organization)->subEventOf($parent)->create(['capacity' => 1]);
    $action = app(ReserveEventCapacity::class);

    $parentResult = $action->handle($parent, (string) Str::uuid());
    $subEventResult = $action->handle($subEvent, (string) Str::uuid());

    expect($parentResult->outcome)->toBe(ReservationOutcome::Accepted);
    expect($subEventResult->outcome)->toBe(ReservationOutcome::Accepted);
});
