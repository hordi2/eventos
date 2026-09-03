<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Actions\CreateOrder;
use App\Domain\Ticketing\Actions\ExpireOrder;
use App\Domain\Ticketing\Actions\InitiateMobileMoneyPayment;
use App\Domain\Ticketing\Actions\ReconcileMobileMoneyPayments;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Support\Capacity\Actions\GetRemainingCapacity;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config(['services.flutterwave.secret' => 'test-secret']);
    Http::fake([
        '*/customers' => Http::response(['data' => ['id' => 'cus_1']], 200),
        '*/payment-methods' => Http::response(['data' => ['id' => 'pmd_1']], 200),
        '*/charges' => Http::response(['data' => ['id' => 'chg_1', 'status' => 'pending']], 200),
    ]);

    $this->organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($this->organization);
    $event = Event::factory()->for($this->organization)->create();
    $this->ticketType = TicketType::factory()->for($this->organization)->create(['event_id' => $event->id]);
    $this->tier = PriceTier::factory()->for($this->ticketType)->for($this->organization)->limited(3)->create();

    $order = app(CreateOrder::class)->handle(
        $this->organization->id, $event->id,
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        [['ticket_type_id' => $this->ticketType->id, 'quantity' => 1]],
        (string) Str::uuid(),
    );
    $this->order = app(InitiateMobileMoneyPayment::class)->handle($order, '225', '0102030405', 'MTN');
});

it('confirme une commande dont le prestataire rapporte un statut succeeded lors de la réconciliation', function (): void {
    Http::fake(['*/charges/chg_1' => Http::response(['data' => ['id' => 'chg_1', 'status' => 'succeeded']], 200)]);

    app(ReconcileMobileMoneyPayments::class)->handle();

    app(CurrentOrganization::class)->set($this->organization);
    expect($this->order->fresh()->status->value)->toBe('paid');
});

it('échoue une commande dont le prestataire rapporte un statut failed lors de la réconciliation', function (): void {
    Http::fake(['*/charges/chg_1' => Http::response(['data' => ['id' => 'chg_1', 'status' => 'failed']], 200)]);

    app(ReconcileMobileMoneyPayments::class)->handle();

    app(CurrentOrganization::class)->set($this->organization);
    expect($this->order->fresh()->status->value)->toBe('failed');

    $remaining = app(GetRemainingCapacity::class)->handle('price_tier', (string) $this->tier->id, $this->tier->quantity);
    expect($remaining)->toBe(3);
});

it('ne touche à rien tant que le prestataire rapporte toujours un statut pending', function (): void {
    Http::fake(['*/charges/chg_1' => Http::response(['data' => ['id' => 'chg_1', 'status' => 'pending']], 200)]);

    app(ReconcileMobileMoneyPayments::class)->handle();

    app(CurrentOrganization::class)->set($this->organization);
    expect($this->order->fresh()->status->value)->toBe('pending');
});

it('journalise pour revue manuelle sans lever d\'exception quand la commande a déjà expiré avant la confirmation tardive', function (): void {
    Log::spy();
    app(CurrentOrganization::class)->set($this->organization);
    $this->order->update(['reserved_until' => now()->subMinute()]);
    app(ExpireOrder::class)->handle($this->order->fresh());

    Http::fake(['*/charges/chg_1' => Http::response(['data' => ['id' => 'chg_1', 'status' => 'succeeded']], 200)]);

    app(ReconcileMobileMoneyPayments::class)->handle();

    app(CurrentOrganization::class)->set($this->organization);
    expect($this->order->fresh()->status->value)->toBe('expired');
    Log::shouldHaveReceived('warning')->once();
});
