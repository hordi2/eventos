<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Actions\CreateOrder;
use App\Domain\Ticketing\Actions\ExpireOrder;
use App\Domain\Ticketing\Actions\MarkOrderPaid;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Jobs\ExpireOrderJob;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Str;

function runExpireOrderJob(int $orderId, int $organizationId): void
{
    (new ExpireOrderJob($orderId, $organizationId))->handle(
        app(CurrentOrganization::class),
        app(ExpireOrder::class),
    );
}

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($this->organization);
    $this->event = Event::factory()->for($this->organization)->create();
    $this->ticketType = TicketType::factory()->for($this->organization)->create(['event_id' => $this->event->id]);
    $this->tier = PriceTier::factory()->for($this->ticketType)->for($this->organization)->limited(3)->create();
});

it('libère la réservation d\'une commande encore en attente à l\'exécution du job', function (): void {
    $order = app(CreateOrder::class)->handle(
        $this->organization->id, $this->event->id,
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        [['ticket_type_id' => $this->ticketType->id, 'quantity' => 1]],
        (string) Str::uuid(),
    );
    $order->update(['reserved_until' => now()->subMinute()]);

    runExpireOrderJob($order->id, $this->organization->id);

    expect($order->fresh()->status->value)->toBe('expired');
});

it('ne fait rien quand la commande a déjà été payée avant l\'exécution du job', function (): void {
    $order = app(CreateOrder::class)->handle(
        $this->organization->id, $this->event->id,
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        [['ticket_type_id' => $this->ticketType->id, 'quantity' => 1]],
        (string) Str::uuid(),
    );
    app(MarkOrderPaid::class)->handle($order, 'stripe', (string) Str::uuid(), $order->total);

    runExpireOrderJob($order->id, $this->organization->id);

    expect($order->fresh()->status->value)->toBe('paid');
});
