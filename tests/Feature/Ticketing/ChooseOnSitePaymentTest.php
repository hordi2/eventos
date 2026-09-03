<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Actions\ChooseOnSitePayment;
use App\Domain\Ticketing\Actions\CreateOrder;
use App\Domain\Ticketing\Actions\MarkOrderPaid;
use App\Domain\Ticketing\InvalidOrderTransitionException;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($this->organization);
    $event = Event::factory()->for($this->organization)->create();
    $ticketType = TicketType::factory()->for($this->organization)->create(['event_id' => $event->id]);
    PriceTier::factory()->for($ticketType)->for($this->organization)->create();

    $this->order = app(CreateOrder::class)->handle(
        $this->organization->id, $event->id,
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
        (string) Str::uuid(),
    );
});

it('bascule une commande en attente vers le paiement à l\'arrivée, sans réservation à durée limitée', function (): void {
    $onSite = app(ChooseOnSitePayment::class)->handle($this->order);

    expect($onSite->status->value)->toBe('payment_on_site');
    expect($onSite->reserved_until)->toBeNull();
});

it('refuse de basculer une commande qui n\'est plus en attente', function (): void {
    app(MarkOrderPaid::class)->handle($this->order, 'stripe', (string) Str::uuid(), $this->order->total);

    app(ChooseOnSitePayment::class)->handle($this->order->fresh());
})->throws(InvalidOrderTransitionException::class);
