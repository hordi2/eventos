<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Actions\ReleasePriceTierCapacity;
use App\Domain\Ticketing\Actions\ReservePriceTierCapacity;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Support\Capacity\Data\ReservationOutcome;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($this->organization);
    $event = Event::factory()->for($this->organization)->create();
    $this->ticketType = TicketType::factory()->for($this->organization)->create(['event_id' => $event->id]);
});

it('réserve une place sur un palier disposant encore de quota', function (): void {
    $tier = PriceTier::factory()->for($this->ticketType)->for($this->organization)->limited(10)->create();

    $result = app(ReservePriceTierCapacity::class)->handle($tier, (string) Str::uuid());

    expect($result->outcome)->toBe(ReservationOutcome::Accepted);
});

it('refuse la réservation quand le quota du palier est atteint, sans liste d\'attente', function (): void {
    $tier = PriceTier::factory()->for($this->ticketType)->for($this->organization)->limited(1)->create();
    $action = app(ReservePriceTierCapacity::class);

    $action->handle($tier, (string) Str::uuid());
    $second = $action->handle($tier, (string) Str::uuid());

    expect($second->outcome)->toBe(ReservationOutcome::Rejected);
});

it('rejoue la même réservation de façon idempotente pour une clé déjà traitée', function (): void {
    $tier = PriceTier::factory()->for($this->ticketType)->for($this->organization)->limited(1)->create();
    $key = (string) Str::uuid();
    $action = app(ReservePriceTierCapacity::class);

    $first = $action->handle($tier, $key);
    $replay = $action->handle($tier, $key);

    expect($first->outcome)->toBe(ReservationOutcome::Accepted);
    expect($replay->outcome)->toBe(ReservationOutcome::Accepted);
});

it('libère une place, la rendant à nouveau disponible', function (): void {
    $tier = PriceTier::factory()->for($this->ticketType)->for($this->organization)->limited(1)->create();
    $key = (string) Str::uuid();

    app(ReservePriceTierCapacity::class)->handle($tier, $key);
    app(ReleasePriceTierCapacity::class)->handle($tier, $key);

    $result = app(ReservePriceTierCapacity::class)->handle($tier, (string) Str::uuid());

    expect($result->outcome)->toBe(ReservationOutcome::Accepted);
});

it('un palier sans quota (illimité) accepte toujours la réservation', function (): void {
    $tier = PriceTier::factory()->for($this->ticketType)->for($this->organization)->create(['quantity' => null]);

    $result = app(ReservePriceTierCapacity::class)->handle($tier, (string) Str::uuid(), 1000);

    expect($result->outcome)->toBe(ReservationOutcome::Accepted);
});
