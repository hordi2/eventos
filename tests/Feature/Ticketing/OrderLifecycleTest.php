<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Actions\CreateOrder;
use App\Domain\Ticketing\Actions\ExpireOrder;
use App\Domain\Ticketing\Actions\FailOrderPayment;
use App\Domain\Ticketing\Actions\MarkOrderPaid;
use App\Domain\Ticketing\Actions\RefundOrder;
use App\Domain\Ticketing\InvalidOrderTransitionException;
use App\Domain\Ticketing\Models\Payment;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketStatus;
use App\Domain\Ticketing\Models\TicketType;
use App\Models\User;
use App\Support\Capacity\Actions\GetRemainingCapacity;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

function ticketingOrderContext(): array
{
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $event = Event::factory()->for($organization)->create();
    $ticketType = TicketType::factory()->for($organization)->create(['event_id' => $event->id, 'total_quantity' => 5]);
    $tier = PriceTier::factory()->for($ticketType)->for($organization)->limited(5)->create();

    $order = app(CreateOrder::class)->handle(
        $organization->id, $event->id,
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        [['ticket_type_id' => $ticketType->id, 'quantity' => 2]],
        (string) Str::uuid(),
    );

    return [$organization, $ticketType, $tier, $order];
}

it('confirme le paiement, émet les billets et libère le créneau de réservation', function (): void {
    [, , , $order] = ticketingOrderContext();

    $paid = app(MarkOrderPaid::class)->handle($order, 'stripe', (string) Str::uuid(), $order->total);

    expect($paid->status->value)->toBe('paid');
    expect($paid->reserved_until)->toBeNull();
    expect($paid->items->first()->tickets)->toHaveCount(2);
    expect($paid->payments)->toHaveCount(1);
});

it('rejoue la confirmation de paiement de façon idempotente pour un provider_payment_id déjà traité', function (): void {
    [, , , $order] = ticketingOrderContext();
    $paymentId = (string) Str::uuid();

    app(MarkOrderPaid::class)->handle($order, 'stripe', $paymentId, $order->total);
    $replay = app(MarkOrderPaid::class)->handle($order->fresh(), 'stripe', $paymentId, $order->total);

    expect($replay->items->first()->tickets)->toHaveCount(2);
    expect(Payment::query()->count())->toBe(1);
});

it('refuse de confirmer le paiement d\'une commande qui n\'est plus en attente', function (): void {
    [, , , $order] = ticketingOrderContext();
    app(MarkOrderPaid::class)->handle($order, 'stripe', (string) Str::uuid(), $order->total);

    app(MarkOrderPaid::class)->handle($order->fresh(), 'stripe', (string) Str::uuid(), $order->total);
})->throws(InvalidOrderTransitionException::class);

it('échoue le paiement et libère immédiatement le stock réservé', function (): void {
    [$organization, $ticketType, $tier, $order] = ticketingOrderContext();

    $failed = app(FailOrderPayment::class)->handle($order, 'stripe', null, 'Carte refusée');

    expect($failed->status->value)->toBe('failed');
    expect($failed->payments->first()->failure_reason)->toBe('Carte refusée');

    $remaining = app(GetRemainingCapacity::class)->handle('price_tier', (string) $tier->id, $tier->quantity);
    expect($remaining)->toBe(5);
});

it('expire une commande non payée à l\'échéance et détecte l\'abandon de panier', function (): void {
    [$organization, $ticketType, $tier, $order] = ticketingOrderContext();
    $order->update(['reserved_until' => now()->subMinute()]);

    $expired = app(ExpireOrder::class)->handle($order);

    expect($expired->status->value)->toBe('expired');
    expect($expired->abandoned_at)->not->toBeNull();

    $remaining = app(GetRemainingCapacity::class)->handle('price_tier', (string) $tier->id, $tier->quantity);
    expect($remaining)->toBe(5);
});

it('n\'enregistre pas d\'abandon quand un paiement avait été tenté avant l\'expiration', function (): void {
    [, , , $order] = ticketingOrderContext();
    app(FailOrderPayment::class)->handle($order, 'stripe', null, 'Timeout prestataire');

    // FailOrderPayment fait déjà passer la commande à "failed" : on simule
    // ici le cas où elle serait restée "pending" malgré une tentative
    // (paiement asynchrone Mobile Money en attente, M5.4) pour vérifier la
    // distinction abandon vs expiration après tentative.
    $order->fresh()->update(['status' => 'pending', 'reserved_until' => now()->subMinute()]);

    $expired = app(ExpireOrder::class)->handle($order->fresh());

    expect($expired->abandoned_at)->toBeNull();
});

it('est idempotent : expirer une commande déjà payée ne fait rien', function (): void {
    [, , , $order] = ticketingOrderContext();
    $paid = app(MarkOrderPaid::class)->handle($order, 'stripe', (string) Str::uuid(), $order->total);

    $result = app(ExpireOrder::class)->handle($paid);

    expect($result->status->value)->toBe('paid');
});

it('rembourse une commande payée, annule ses billets et libère le stock', function (): void {
    [$organization, $ticketType, $tier, $order] = ticketingOrderContext();
    $paid = app(MarkOrderPaid::class)->handle($order, 'stripe', (string) Str::uuid(), $order->total);

    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);

    $refunded = app(RefundOrder::class)->handle($paid, $admin);

    expect($refunded->status->value)->toBe('refunded');
    expect($refunded->items->first()->tickets->pluck('status')->unique()->all())->toBe([TicketStatus::Cancelled]);

    $remaining = app(GetRemainingCapacity::class)->handle('price_tier', (string) $tier->id, $tier->quantity);
    expect($remaining)->toBe(5);
});

it('refuse le remboursement à un rôle qui n\'a pas la capacité refundTickets', function (): void {
    [$organization, , , $order] = ticketingOrderContext();
    $paid = app(MarkOrderPaid::class)->handle($order, 'stripe', (string) Str::uuid(), $order->total);

    $editor = User::factory()->create();
    $editor->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Editor]);

    app(RefundOrder::class)->handle($paid, $editor);
})->throws(AuthorizationException::class);

it('refuse de rembourser une commande qui n\'est pas payée', function (): void {
    [$organization, , , $order] = ticketingOrderContext();

    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);

    app(RefundOrder::class)->handle($order, $admin);
})->throws(InvalidOrderTransitionException::class);
