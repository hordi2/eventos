<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Organization;
use App\Support\Capacity\Actions\ReserveCapacity;
use App\Support\Capacity\Data\ReservationOutcome;
use App\Support\Capacity\Models\CapacityHold;
use App\Support\Capacity\Models\WaitlistEntry;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($this->organization);
});

it('accepte une réservation quand la capacité est disponible', function (): void {
    $result = app(ReserveCapacity::class)->handle(
        organizationId: $this->organization->id,
        holderType: 'event',
        holderId: '1',
        capacityLimit: 50,
        reservationKey: (string) Str::uuid(),
    );

    expect($result->outcome)->toBe(ReservationOutcome::Accepted);
    expect(CapacityHold::query()->count())->toBe(1);
});

it('accepte sans limite quand aucune capacité n\'est définie', function (): void {
    $result = app(ReserveCapacity::class)->handle(
        organizationId: $this->organization->id,
        holderType: 'event',
        holderId: '1',
        capacityLimit: null,
        reservationKey: (string) Str::uuid(),
    );

    expect($result->outcome)->toBe(ReservationOutcome::Accepted);
});

it('bascule sur liste d\'attente avec la bonne position quand la capacité est atteinte', function (): void {
    $action = app(ReserveCapacity::class);

    $action->handle($this->organization->id, 'event', '1', 1, (string) Str::uuid());
    $second = $action->handle($this->organization->id, 'event', '1', 1, (string) Str::uuid(), allowWaitlist: true);
    $third = $action->handle($this->organization->id, 'event', '1', 1, (string) Str::uuid(), allowWaitlist: true);

    expect($second->outcome)->toBe(ReservationOutcome::Waitlisted);
    expect($second->waitlistPosition)->toBe(1);
    expect($third->outcome)->toBe(ReservationOutcome::Waitlisted);
    expect($third->waitlistPosition)->toBe(2);
    expect(WaitlistEntry::query()->count())->toBe(2);
});

it('refuse quand la capacité est atteinte et qu\'aucune liste d\'attente n\'est autorisée', function (): void {
    $action = app(ReserveCapacity::class);

    $action->handle($this->organization->id, 'event', '1', 1, (string) Str::uuid());
    $second = $action->handle($this->organization->id, 'event', '1', 1, (string) Str::uuid(), allowWaitlist: false);

    expect($second->outcome)->toBe(ReservationOutcome::Rejected);
    expect(WaitlistEntry::query()->count())->toBe(0);
});

it('est idempotent : la même clé de réservation ne crée jamais de doublon', function (): void {
    $action = app(ReserveCapacity::class);
    $key = (string) Str::uuid();

    $first = $action->handle($this->organization->id, 'event', '1', 50, $key);
    $replay = $action->handle($this->organization->id, 'event', '1', 50, $key);

    expect($first->outcome)->toBe(ReservationOutcome::Accepted);
    expect($replay->outcome)->toBe(ReservationOutcome::Accepted);
    expect(CapacityHold::query()->count())->toBe(1);
});

it('est idempotent pour une clé déjà placée en liste d\'attente', function (): void {
    $action = app(ReserveCapacity::class);

    $action->handle($this->organization->id, 'event', '1', 1, (string) Str::uuid());
    $key = (string) Str::uuid();

    $first = $action->handle($this->organization->id, 'event', '1', 1, $key, allowWaitlist: true);
    $replay = $action->handle($this->organization->id, 'event', '1', 1, $key, allowWaitlist: true);

    expect($first->waitlistPosition)->toBe(1);
    expect($replay->waitlistPosition)->toBe(1);
    expect(WaitlistEntry::query()->count())->toBe(1);
});

it('isole la capacité par holder : deux événements ne se gênent jamais', function (): void {
    $action = app(ReserveCapacity::class);

    $action->handle($this->organization->id, 'event', '1', 1, (string) Str::uuid());
    $other = $action->handle($this->organization->id, 'event', '2', 1, (string) Str::uuid());

    expect($other->outcome)->toBe(ReservationOutcome::Accepted);
});
