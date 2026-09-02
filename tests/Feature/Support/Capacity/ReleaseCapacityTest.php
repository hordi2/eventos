<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Organization;
use App\Support\Capacity\Actions\ReleaseCapacity;
use App\Support\Capacity\Actions\ReserveCapacity;
use App\Support\Capacity\Events\WaitlistEntryPromoted;
use App\Support\Capacity\Models\CapacityHold;
use App\Support\Capacity\Models\CapacityHoldStatus;
use App\Support\Capacity\Models\WaitlistEntry;
use App\Support\Capacity\Models\WaitlistEntryStatus;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($this->organization);
});

it('libère une place tenue et promeut le premier de la liste d\'attente', function (): void {
    Event::fake([WaitlistEntryPromoted::class]);

    $reserve = app(ReserveCapacity::class);
    $heldKey = (string) Str::uuid();
    $waitlistedKey = (string) Str::uuid();

    $reserve->handle($this->organization->id, 'event', '1', 1, $heldKey);
    $reserve->handle($this->organization->id, 'event', '1', 1, $waitlistedKey, allowWaitlist: true);

    app(ReleaseCapacity::class)->handle('event', '1', $heldKey);

    expect(CapacityHold::query()->where('reservation_key', $heldKey)->first()->status)
        ->toBe(CapacityHoldStatus::Released);
    expect(CapacityHold::query()->where('reservation_key', $waitlistedKey)->first()->status)
        ->toBe(CapacityHoldStatus::Held);

    $promoted = WaitlistEntry::query()->where('reservation_key', $waitlistedKey)->first();
    expect($promoted->status)->toBe(WaitlistEntryStatus::Promoted);
    expect($promoted->promoted_at)->not->toBeNull();

    Event::assertDispatched(WaitlistEntryPromoted::class, fn (WaitlistEntryPromoted $event): bool => $event->entry->id === $promoted->id);
});

it('ne promeut personne si la première entrée en attente demande plus de places que ce qui est libéré', function (): void {
    $reserve = app(ReserveCapacity::class);
    $heldKey = (string) Str::uuid();
    $waitlistedKey = (string) Str::uuid();

    $reserve->handle($this->organization->id, 'event', '1', 1, $heldKey);
    $reserve->handle($this->organization->id, 'event', '1', 1, $waitlistedKey, quantity: 2, allowWaitlist: true);

    app(ReleaseCapacity::class)->handle('event', '1', $heldKey);

    $entry = WaitlistEntry::query()->where('reservation_key', $waitlistedKey)->first();
    expect($entry->status)->toBe(WaitlistEntryStatus::Waiting);
});

it('est idempotent : relâcher deux fois la même clé ne promeut pas deux fois', function (): void {
    $reserve = app(ReserveCapacity::class);
    $heldKey = (string) Str::uuid();
    $waitlistedKey = (string) Str::uuid();

    $reserve->handle($this->organization->id, 'event', '1', 1, $heldKey);
    $reserve->handle($this->organization->id, 'event', '1', 1, $waitlistedKey, allowWaitlist: true);

    $release = app(ReleaseCapacity::class);
    $release->handle('event', '1', $heldKey);
    $release->handle('event', '1', $heldKey);

    expect(CapacityHold::query()->where('status', CapacityHoldStatus::Held)->count())->toBe(1);
});
