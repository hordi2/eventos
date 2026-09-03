<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Actions\CreateOrder;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Domain\Ticketing\TicketsUnavailableException;
use App\Jobs\ExpireOrderJob;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($this->organization);
    $this->event = Event::factory()->for($this->organization)->create();
});

it('crée une commande en attente et réserve le stock pour un billet payant', function (): void {
    Bus::fake();

    $ticketType = TicketType::factory()->for($this->organization)->create(['event_id' => $this->event->id]);
    $tier = PriceTier::factory()->for($ticketType)->for($this->organization)->limited(10)->create();

    $order = app(CreateOrder::class)->handle(
        $this->organization->id,
        $this->event->id,
        ['name' => 'Alice Invitée', 'email' => 'ALICE@Example.com'],
        [['ticket_type_id' => $ticketType->id, 'quantity' => 2]],
        (string) Str::uuid(),
    );

    expect($order->status)->toBe(OrderStatus::Pending);
    expect($order->buyer_email)->toBe('alice@example.com');
    expect($order->reserved_until)->not->toBeNull();
    expect($order->items)->toHaveCount(1);
    expect($order->items->first()->quantity)->toBe(2);
    expect($order->items->first()->price_tier_id)->toBe($tier->id);
    expect($order->total->amountMinor())->toBe($tier->amount->amountMinor() * 2);

    Bus::assertDispatched(ExpireOrderJob::class, fn (ExpireOrderJob $job): bool => $job->orderId === $order->id);
});

it('crée une commande pour un billet gratuit sans réserver de palier', function (): void {
    $ticketType = TicketType::factory()->for($this->organization)->free()->create(['event_id' => $this->event->id]);

    $order = app(CreateOrder::class)->handle(
        $this->organization->id,
        $this->event->id,
        ['name' => 'Bob', 'email' => 'bob@example.com'],
        [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
        (string) Str::uuid(),
    );

    expect($order->total->isZero())->toBeTrue();
    expect($order->items->first()->price_tier_id)->toBeNull();
});

it('rejoue la même commande de façon idempotente pour une clé de réservation déjà utilisée', function (): void {
    $ticketType = TicketType::factory()->for($this->organization)->create(['event_id' => $this->event->id]);
    PriceTier::factory()->for($ticketType)->for($this->organization)->create();
    $key = (string) Str::uuid();

    $first = app(CreateOrder::class)->handle(
        $this->organization->id, $this->event->id,
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
        $key,
    );

    $replay = app(CreateOrder::class)->handle(
        $this->organization->id, $this->event->id,
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
        $key,
    );

    expect($replay->id)->toBe($first->id);
    expect(Order::query()->count())->toBe(1);
});

it('refuse la commande quand le quota du palier est atteint', function (): void {
    $ticketType = TicketType::factory()->for($this->organization)->create(['event_id' => $this->event->id]);
    PriceTier::factory()->for($ticketType)->for($this->organization)->limited(1)->create();

    app(CreateOrder::class)->handle(
        $this->organization->id, $this->event->id,
        ['name' => 'Premier', 'email' => 'premier@example.com'],
        [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
        (string) Str::uuid(),
    );

    app(CreateOrder::class)->handle(
        $this->organization->id, $this->event->id,
        ['name' => 'Second', 'email' => 'second@example.com'],
        [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
        (string) Str::uuid(),
    );
})->throws(TicketsUnavailableException::class);

it('refuse la commande quand le quota global du type de billet est atteint', function (): void {
    $ticketType = TicketType::factory()->for($this->organization)->create(['event_id' => $this->event->id, 'total_quantity' => 1]);
    PriceTier::factory()->for($ticketType)->for($this->organization)->create();

    app(CreateOrder::class)->handle(
        $this->organization->id, $this->event->id,
        ['name' => 'Premier', 'email' => 'premier@example.com'],
        [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
        (string) Str::uuid(),
    );

    app(CreateOrder::class)->handle(
        $this->organization->id, $this->event->id,
        ['name' => 'Second', 'email' => 'second@example.com'],
        [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
        (string) Str::uuid(),
    );
})->throws(TicketsUnavailableException::class);

it('refuse la commande quand aucun palier n\'est actif et ne laisse aucune trace en base', function (): void {
    $ticketType = TicketType::factory()->for($this->organization)->create(['event_id' => $this->event->id]);
    PriceTier::factory()->for($ticketType)->for($this->organization)->expired()->create();

    try {
        app(CreateOrder::class)->handle(
            $this->organization->id, $this->event->id,
            ['name' => 'Alice', 'email' => 'alice@example.com'],
            [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
            (string) Str::uuid(),
        );
    } catch (TicketsUnavailableException) {
        // attendu
    }

    expect(Order::query()->count())->toBe(0);
});

it('respecte les bornes min/max par commande du type de billet', function (): void {
    $ticketType = TicketType::factory()->for($this->organization)->create([
        'event_id' => $this->event->id,
        'min_per_order' => 2,
        'max_per_order' => 4,
    ]);
    PriceTier::factory()->for($ticketType)->for($this->organization)->create();

    app(CreateOrder::class)->handle(
        $this->organization->id, $this->event->id,
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
        (string) Str::uuid(),
    );
})->throws(InvalidArgumentException::class);

it('refuse une commande sans acheteur valide', function (): void {
    $ticketType = TicketType::factory()->for($this->organization)->free()->create(['event_id' => $this->event->id]);

    app(CreateOrder::class)->handle(
        $this->organization->id, $this->event->id,
        ['name' => '', 'email' => 'alice@example.com'],
        [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
        (string) Str::uuid(),
    );
})->throws(InvalidArgumentException::class);
