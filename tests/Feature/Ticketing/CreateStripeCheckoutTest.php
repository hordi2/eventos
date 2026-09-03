<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Actions\CreateOrder;
use App\Domain\Ticketing\Actions\CreateStripeCheckout;
use App\Domain\Ticketing\Actions\MarkOrderPaid;
use App\Domain\Ticketing\InvalidOrderTransitionException;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Support\Money;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Payments\CardCheckoutProvider;
use Illuminate\Support\Str;

/**
 * Double de test : le SDK Stripe fait ses propres appels HTTP (Guzzle
 * interne), rien à "fake" côté Laravel — seule l'interface
 * CardCheckoutProvider est substituable (voir son propre docblock).
 */
final class FakeCardCheckoutProvider implements CardCheckoutProvider
{
    /** @var list<array{order_id: int, amount_minor: int, currency: string}> */
    public array $created = [];

    public function createCheckoutSession(int $orderId, Money $amount, string $successUrl, string $cancelUrl): string
    {
        $this->created[] = ['order_id' => $orderId, 'amount_minor' => $amount->amountMinor(), 'currency' => $amount->currency()];

        return "https://checkout.stripe.com/pay/cs_test_{$orderId}";
    }
}

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($this->organization);
    $event = Event::factory()->for($this->organization)->create();
    $ticketType = TicketType::factory()->for($this->organization)->create(['event_id' => $event->id]);
    $tier = PriceTier::factory()->for($ticketType)->for($this->organization)->create();

    $this->order = app(CreateOrder::class)->handle(
        $this->organization->id, $event->id,
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
        (string) Str::uuid(),
    );
});

it('crée une session de paiement Stripe pour une commande en attente', function (): void {
    $fake = new FakeCardCheckoutProvider;
    $this->app->bind(CardCheckoutProvider::class, fn () => $fake);

    $url = app(CreateStripeCheckout::class)->handle($this->order, 'https://example.com/success', 'https://example.com/cancel');

    expect($url)->toBe("https://checkout.stripe.com/pay/cs_test_{$this->order->id}");
    expect($fake->created[0]['order_id'])->toBe($this->order->id);
    expect($fake->created[0]['amount_minor'])->toBe($this->order->total->amountMinor());
});

it('refuse de créer une session pour une commande qui n\'est plus en attente', function (): void {
    $fake = new FakeCardCheckoutProvider;
    $this->app->bind(CardCheckoutProvider::class, fn () => $fake);
    app(MarkOrderPaid::class)->handle($this->order, 'stripe', (string) Str::uuid(), $this->order->total);

    app(CreateStripeCheckout::class)->handle($this->order->fresh(), 'https://example.com/success', 'https://example.com/cancel');
})->throws(InvalidOrderTransitionException::class);
