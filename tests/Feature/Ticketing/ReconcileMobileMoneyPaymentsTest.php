<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Actions\CreateOrder;
use App\Domain\Ticketing\Actions\ExpireOrder;
use App\Domain\Ticketing\Actions\InitiateMobileMoneyPayment;
use App\Domain\Ticketing\Actions\ReconcileMobileMoneyPayments;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\Payment;
use App\Domain\Ticketing\Models\PaymentStatus;
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

    // La tâche planifiée tourne sans qu'aucune organisation ne soit
    // positionnée au départ (voir ReconcileMobileMoneyPayments) : un
    // résidu laissé par la mise en place du test masquerait le vrai
    // comportement, cloisonné organisation par organisation.
    app(CurrentOrganization::class)->clear();
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
    app(CurrentOrganization::class)->clear();

    Http::fake(['*/charges/chg_1' => Http::response(['data' => ['id' => 'chg_1', 'status' => 'succeeded']], 200)]);

    app(ReconcileMobileMoneyPayments::class)->handle();

    app(CurrentOrganization::class)->set($this->organization);
    expect($this->order->fresh()->status->value)->toBe('expired');
    Log::shouldHaveReceived('warning')->once();
});

it('réconcilie les paiements en attente de plusieurs organisations en une seule passe', function (): void {
    // Fixture construite directement par factory plutôt qu'en repassant
    // par InitiateMobileMoneyPayment : Http::fake() résout toujours le
    // premier stub enregistré pour un motif donné (le "*/charges" de
    // beforeEach), donc un second appel de fixture ne peut pas lui faire
    // renvoyer un identifiant de charge distinct pour cette seconde
    // organisation.
    $otherOrganization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($otherOrganization);
    $otherEvent = Event::factory()->for($otherOrganization)->create();
    $otherOrder = Order::factory()->for($otherOrganization)->create(['event_id' => $otherEvent->id]);
    Payment::factory()->for($otherOrganization)->create([
        'order_id' => $otherOrder->id,
        'provider' => 'flutterwave',
        'provider_payment_id' => 'chg_2',
        'status' => PaymentStatus::Pending,
    ]);
    app(CurrentOrganization::class)->clear();

    Http::fake([
        '*/charges/chg_1' => Http::response(['data' => ['id' => 'chg_1', 'status' => 'succeeded']], 200),
        '*/charges/chg_2' => Http::response(['data' => ['id' => 'chg_2', 'status' => 'succeeded']], 200),
    ]);

    app(ReconcileMobileMoneyPayments::class)->handle();

    app(CurrentOrganization::class)->set($this->organization);
    expect($this->order->fresh()->status->value)->toBe('paid');

    app(CurrentOrganization::class)->set($otherOrganization);
    expect($otherOrder->fresh()->status->value)->toBe('paid');
});

it('une erreur sur un paiement n\'empêche pas la réconciliation des autres', function (): void {
    Log::spy();

    app(CurrentOrganization::class)->set($this->organization);
    $badOrder = Order::factory()->for($this->organization)->create(['event_id' => $this->ticketType->event_id]);
    Payment::factory()->for($this->organization)->create([
        'order_id' => $badOrder->id,
        'provider' => 'flutterwave',
        'provider_payment_id' => 'chg_bad',
        'status' => PaymentStatus::Pending,
    ]);
    app(CurrentOrganization::class)->clear();

    Http::fake([
        '*/charges/chg_1' => Http::response(['data' => ['id' => 'chg_1', 'status' => 'succeeded']], 200),
        '*/charges/chg_bad' => Http::response(null, 500),
    ]);

    app(ReconcileMobileMoneyPayments::class)->handle();

    app(CurrentOrganization::class)->set($this->organization);
    expect($this->order->fresh()->status->value)->toBe('paid');
    Log::shouldHaveReceived('error')->once();
});
