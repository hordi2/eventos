<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Actions\DetermineActivePriceTier;
use App\Domain\Ticketing\Actions\ReservePriceTierCapacity;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($this->organization);
    $this->event = Event::factory()->for($this->organization)->create();
    $this->ticketType = TicketType::factory()->for($this->organization)->create(['event_id' => $this->event->id]);
});

it('sélectionne le palier actif quand une seule fenêtre de dates couvre l\'instant donné', function (): void {
    PriceTier::factory()->for($this->ticketType)->for($this->organization)
        ->expired()->create(['name' => 'Early bird', 'position' => 0]);
    $normal = PriceTier::factory()->for($this->ticketType)->for($this->organization)
        ->create(['name' => 'Normal', 'position' => 1]);

    $active = app(DetermineActivePriceTier::class)->handle($this->ticketType);

    expect($active->id)->toBe($normal->id);
});

it('bascule sur le palier suivant quand le quota du premier est atteint', function (): void {
    $earlyBird = PriceTier::factory()->for($this->ticketType)->for($this->organization)
        ->limited(1)->create(['name' => 'Early bird', 'position' => 0]);
    $normal = PriceTier::factory()->for($this->ticketType)->for($this->organization)
        ->create(['name' => 'Normal', 'position' => 1]);

    app(ReservePriceTierCapacity::class)->handle($earlyBird, (string) Str::uuid());

    $active = app(DetermineActivePriceTier::class)->handle($this->ticketType);

    expect($active->id)->toBe($normal->id);
});

it('ignore un palier qui n\'a pas encore commencé', function (): void {
    PriceTier::factory()->for($this->ticketType)->for($this->organization)
        ->upcoming()->create(['name' => 'Tardif', 'position' => 0]);
    $normal = PriceTier::factory()->for($this->ticketType)->for($this->organization)
        ->create(['name' => 'Normal', 'position' => 1]);

    $active = app(DetermineActivePriceTier::class)->handle($this->ticketType);

    expect($active->id)->toBe($normal->id);
});

it('retourne null quand aucun palier n\'est actif', function (): void {
    PriceTier::factory()->for($this->ticketType)->for($this->organization)->expired()->create();

    $active = app(DetermineActivePriceTier::class)->handle($this->ticketType);

    expect($active)->toBeNull();
});

it('respecte l\'instant explicitement fourni plutôt que l\'heure courante', function (): void {
    $tier = PriceTier::factory()->for($this->ticketType)->for($this->organization)
        ->create(['starts_at' => CarbonImmutable::parse('2026-01-01'), 'ends_at' => CarbonImmutable::parse('2026-01-31')]);

    $active = app(DetermineActivePriceTier::class)->handle($this->ticketType, CarbonImmutable::parse('2026-01-15'));
    $inactive = app(DetermineActivePriceTier::class)->handle($this->ticketType, CarbonImmutable::parse('2026-02-01'));

    expect($active->id)->toBe($tier->id);
    expect($inactive)->toBeNull();
});
