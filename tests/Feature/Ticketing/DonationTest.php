<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Actions\CreateOrder;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Support\Money;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($this->organization);
    $event = Event::factory()->for($this->organization)->create();
    $this->ticketType = TicketType::factory()->for($this->organization)->create(['event_id' => $event->id]);
    $this->tier = PriceTier::factory()->for($this->ticketType)->for($this->organization)->create();
    $this->event = $event;
});

it('ajoute un don au moment de l\'achat du billet et l\'inclut dans le total', function (): void {
    $order = app(CreateOrder::class)->handle(
        $this->organization->id, $this->event->id,
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        [['ticket_type_id' => $this->ticketType->id, 'quantity' => 1]],
        (string) Str::uuid(),
        donation: Money::fromMinorUnits(500, 'EUR'),
        donationCause: 'Fonds bourses étudiantes',
    );

    expect($order->donations)->toHaveCount(1);
    expect($order->donations->first()->amount->amountMinor())->toBe(500);
    expect($order->donations->first()->cause)->toBe('Fonds bourses étudiantes');
    expect($order->total->amountMinor())->toBe($this->tier->amount->amountMinor() + 500);
});

it('ne crée aucun don quand aucun montant n\'est fourni', function (): void {
    $order = app(CreateOrder::class)->handle(
        $this->organization->id, $this->event->id,
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        [['ticket_type_id' => $this->ticketType->id, 'quantity' => 1]],
        (string) Str::uuid(),
    );

    expect($order->donations)->toHaveCount(0);
    expect($order->total->amountMinor())->toBe($this->tier->amount->amountMinor());
});

it('accepte un montant de don libre, sans plafond ni palier imposé', function (): void {
    $order = app(CreateOrder::class)->handle(
        $this->organization->id, $this->event->id,
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        [['ticket_type_id' => $this->ticketType->id, 'quantity' => 1]],
        (string) Str::uuid(),
        donation: Money::fromMinorUnits(123456, 'EUR'),
    );

    expect($order->donations->first()->amount->amountMinor())->toBe(123456);
});

it('refuse un don à montant nul ou négatif', function (): void {
    app(CreateOrder::class)->handle(
        $this->organization->id, $this->event->id,
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        [['ticket_type_id' => $this->ticketType->id, 'quantity' => 1]],
        (string) Str::uuid(),
        donation: Money::zero('EUR'),
    );
})->throws(InvalidArgumentException::class);
