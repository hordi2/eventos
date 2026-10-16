<?php

declare(strict_types=1);

use App\Domain\Organization\Models\AuditLog;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Ticketing\Actions\MarkOrderPaid;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Domain\Ticketing\Models\TicketStatus;
use App\Models\User;
use App\Support\Money;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Payments\CardCheckoutProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class RecordingFakeCardCheckoutProvider implements CardCheckoutProvider
{
    /** @var list<array{payment_intent_id: string, amount_minor: int, currency: string}> */
    public array $refunds = [];

    public function createCheckoutSession(int $orderId, int $organizationId, Money $amount, string $successUrl, string $cancelUrl): string
    {
        return "https://checkout.stripe.com/pay/cs_test_{$orderId}";
    }

    public function refund(string $paymentIntentId, Money $amount): string
    {
        $this->refunds[] = ['payment_intent_id' => $paymentIntentId, 'amount_minor' => $amount->amountMinor(), 'currency' => $amount->currency()];

        return 're_test_'.count($this->refunds);
    }
}

/**
 * Une commande payée, pour un organisateur Admin du portefeuille.
 *
 * @return array{admin: User, order: Order}
 */
function paidOrderForRefund(string $provider = 'stripe'): array
{
    ['organization' => $organization, 'event' => $event, 'ticketType' => $ticketType] = makeGuestTicketedEvent();

    test()->post("/billets/{$organization->slug}/{$event->slug}", [
        'checkout_token' => (string) Str::uuid(),
        'buyer_name' => 'Alice Kouassi',
        'buyer_email' => 'alice@example.com',
        'items' => [$ticketType->id => 1],
    ]);

    app(CurrentOrganization::class)->set($organization);
    $order = Order::query()->where('buyer_email', 'alice@example.com')->firstOrFail();
    app(MarkOrderPaid::class)->handle($order, $provider, 'pi_test_'.Str::random(8), $order->total);

    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);
    $fresh = $order->fresh(['items.tickets', 'payments']);
    app(CurrentOrganization::class)->clear();

    return ['admin' => $admin, 'order' => $fresh];
}

it('rembourse intégralement une commande payée par carte et annule ses billets', function (): void {
    $fake = new RecordingFakeCardCheckoutProvider;
    app()->bind(CardCheckoutProvider::class, fn () => $fake);
    ['admin' => $admin, 'order' => $order] = paidOrderForRefund();
    $paymentIntentId = $order->payments->sole()->provider_payment_id;

    test()->actingAs($admin)->post("/events/{$order->event_id}/orders/{$order->id}/refund")
        ->assertSessionHas('status', 'order-refunded');

    $fresh = $order->fresh(['items.tickets']);
    expect($fresh->status)->toBe(OrderStatus::Refunded)
        ->and($fresh->refunded_amount_minor)->toBe($order->total->amountMinor())
        ->and($fresh->refunded_at)->not->toBeNull();

    foreach ($fresh->items as $item) {
        foreach ($item->tickets as $ticket) {
            expect($ticket->status)->toBe(TicketStatus::Cancelled);
        }
    }

    expect($fake->refunds)->toHaveCount(1);
    expect($fake->refunds[0]['payment_intent_id'])->toBe($paymentIntentId);
    expect($fake->refunds[0]['amount_minor'])->toBe($order->total->amountMinor());

    expect(AuditLog::query()->where('action', 'order.refunded')->sole()->metadata['full'])->toBeTrue();
});

it('rembourse partiellement sans toucher aux billets ni à la capacité', function (): void {
    $fake = new RecordingFakeCardCheckoutProvider;
    app()->bind(CardCheckoutProvider::class, fn () => $fake);
    ['admin' => $admin, 'order' => $order] = paidOrderForRefund();
    $half = intdiv($order->total->amountMinor(), 2);

    test()->actingAs($admin)
        ->post("/events/{$order->event_id}/orders/{$order->id}/refund", ['amount' => number_format($half / 100, 2, '.', '')])
        ->assertSessionHas('status', 'order-refunded');

    $fresh = $order->fresh(['items.tickets']);
    expect($fresh->status)->toBe(OrderStatus::Paid)
        ->and($fresh->refunded_amount_minor)->toBe($half)
        ->and($fresh->refunded_at)->toBeNull();

    foreach ($fresh->items as $item) {
        foreach ($item->tickets as $ticket) {
            expect($ticket->status)->not->toBe(TicketStatus::Cancelled);
        }
    }

    expect($fake->refunds[0]['amount_minor'])->toBe($half);
});

it('refuse un remboursement qui dépasse ce qu\'il reste à rembourser', function (): void {
    $fake = new RecordingFakeCardCheckoutProvider;
    app()->bind(CardCheckoutProvider::class, fn () => $fake);
    ['admin' => $admin, 'order' => $order] = paidOrderForRefund();
    $tooMuch = ($order->total->amountMinor() + 100) / 100;

    test()->actingAs($admin)
        ->post("/events/{$order->event_id}/orders/{$order->id}/refund", ['amount' => number_format($tooMuch, 2, '.', '')])
        ->assertSessionHasErrors('amount');

    expect($fake->refunds)->toBe([]);
    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('rembourse par Flutterwave en appelant le prestataire Mobile Money', function (): void {
    Http::fake(['*/charges/*/refund' => Http::response(['data' => ['id' => 'rf_fw_1']], 200)]);
    ['admin' => $admin, 'order' => $order] = paidOrderForRefund('flutterwave');

    test()->actingAs($admin)->post("/events/{$order->event_id}/orders/{$order->id}/refund")
        ->assertSessionHas('status', 'order-refunded');

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/refund'));
});

it('rembourse en espèces sans appeler aucun prestataire', function (): void {
    Http::fake();
    ['admin' => $admin, 'order' => $order] = paidOrderForRefund('cash');

    test()->actingAs($admin)->post("/events/{$order->event_id}/orders/{$order->id}/refund")
        ->assertSessionHas('status', 'order-refunded');

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded);
    Http::assertNothingSent();
});

it('refuse à qui n\'a pas le droit de rembourser', function (): void {
    ['order' => $order] = paidOrderForRefund();

    app(CurrentOrganization::class)->set($order->organization_id);
    $viewer = User::factory()->create();
    $viewer->memberships()->create(['organization_id' => $order->organization_id, 'role' => MembershipRole::Editor]);
    app(CurrentOrganization::class)->clear();

    test()->actingAs($viewer)->post("/events/{$order->event_id}/orders/{$order->id}/refund")->assertForbidden();
});

it('liste les commandes avec ce qu\'il reste à rembourser', function (): void {
    ['admin' => $admin, 'order' => $order] = paidOrderForRefund();

    test()->actingAs($admin)->get("/events/{$order->event_id}/orders")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('orders.0.buyerEmail', 'alice@example.com')
            ->where('orders.0.canRefund', true));
});
