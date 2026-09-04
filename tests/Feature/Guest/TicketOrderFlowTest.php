<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Actions\MarkOrderPaid;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Support\Money;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Payments\CardCheckoutProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function makeGuestTicketedEvent(): array
{
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $event = Event::factory()->for($organization)->published()->create();
    $ticketType = TicketType::factory()->for($organization)->create(['event_id' => $event->id, 'name' => 'Billet standard']);
    $tier = PriceTier::factory()->for($ticketType)->for($organization)->limited(10)->create(['name' => 'Normal', 'amount' => Money::fromMinorUnits(2000, 'EUR')]);
    app(CurrentOrganization::class)->clear();

    return ['organization' => $organization, 'event' => $event, 'ticketType' => $ticketType, 'tier' => $tier];
}

it('affiche les types de billets disponibles sur la page publique', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestTicketedEvent();

    $response = $this->get("/billets/{$organization->slug}/{$event->slug}");

    $response->assertOk();
    $response->assertSee('Billet standard');
});

it('crée une commande et redirige vers le choix du moyen de paiement', function (): void {
    ['organization' => $organization, 'event' => $event, 'ticketType' => $ticketType] = makeGuestTicketedEvent();

    $response = $this->post("/billets/{$organization->slug}/{$event->slug}", [
        'checkout_token' => (string) Str::uuid(),
        'buyer_name' => 'Alice Kouassi',
        'buyer_email' => 'alice@example.com',
        'items' => [$ticketType->id => 2],
    ]);

    app(CurrentOrganization::class)->set($organization);
    $order = Order::query()->where('buyer_email', 'alice@example.com')->firstOrFail();

    expect($order->status->value)->toBe('pending');
    expect($order->items->first()->quantity)->toBe(2);
    $response->assertRedirect("/billets/{$organization->slug}/{$event->slug}/{$order->reservation_key}/paiement");
});

it('inclut un don libre dans le total de la commande', function (): void {
    ['organization' => $organization, 'event' => $event, 'ticketType' => $ticketType] = makeGuestTicketedEvent();

    $this->post("/billets/{$organization->slug}/{$event->slug}", [
        'checkout_token' => (string) Str::uuid(),
        'buyer_name' => 'Alice',
        'buyer_email' => 'alice@example.com',
        'items' => [$ticketType->id => 1],
        'donation_amount' => '5.50',
    ]);

    app(CurrentOrganization::class)->set($organization);
    $order = Order::query()->where('buyer_email', 'alice@example.com')->firstOrFail();

    expect($order->donations)->toHaveCount(1);
    expect($order->donations->first()->amount->amountMinor())->toBe(550);
    expect($order->total->amountMinor())->toBe(2000 + 550);
});

it('confirme immédiatement une réservation avec paiement à l\'arrivée', function (): void {
    ['organization' => $organization, 'event' => $event, 'ticketType' => $ticketType] = makeGuestTicketedEvent();

    $this->post("/billets/{$organization->slug}/{$event->slug}", [
        'checkout_token' => (string) Str::uuid(),
        'buyer_name' => 'Alice',
        'buyer_email' => 'alice@example.com',
        'items' => [$ticketType->id => 1],
    ]);

    app(CurrentOrganization::class)->set($organization);
    $order = Order::query()->where('buyer_email', 'alice@example.com')->firstOrFail();
    app(CurrentOrganization::class)->clear();

    $onSite = $this->post("/billets/{$organization->slug}/{$event->slug}/{$order->reservation_key}/paiement/arrivee");
    $onSite->assertRedirect("/billets/{$organization->slug}/{$event->slug}/{$order->reservation_key}/statut");

    $confirmation = $this->get("/billets/{$organization->slug}/{$event->slug}/{$order->reservation_key}/statut");
    $confirmation->assertRedirect("/billets/{$organization->slug}/{$event->slug}/{$order->reservation_key}/confirmation");

    $page = $this->get("/billets/{$organization->slug}/{$event->slug}/{$order->reservation_key}/confirmation");
    $page->assertOk();
    $page->assertSee('Réservation confirmée');
});

it('affiche l\'écran d\'attente pour une commande Mobile Money en attente puis confirme après webhook', function (): void {
    config(['services.flutterwave.secret' => 'test-secret']);
    Http::fake([
        '*/customers' => Http::response(['data' => ['id' => 'cus_1']], 200),
        '*/payment-methods' => Http::response(['data' => ['id' => 'pmd_1']], 200),
        '*/charges' => Http::response(['data' => ['id' => 'chg_1', 'status' => 'pending']], 200),
    ]);

    ['organization' => $organization, 'event' => $event, 'ticketType' => $ticketType] = makeGuestTicketedEvent();

    $this->post("/billets/{$organization->slug}/{$event->slug}", [
        'checkout_token' => (string) Str::uuid(),
        'buyer_name' => 'Alice',
        'buyer_email' => 'alice@example.com',
        'items' => [$ticketType->id => 1],
    ]);

    app(CurrentOrganization::class)->set($organization);
    $order = Order::query()->where('buyer_email', 'alice@example.com')->firstOrFail();
    app(CurrentOrganization::class)->clear();

    $mm = $this->post("/billets/{$organization->slug}/{$event->slug}/{$order->reservation_key}/paiement/mobile-money", [
        'country_code' => '225',
        'phone_number' => '0102030405',
        'network' => 'MTN',
    ]);
    $mm->assertRedirect("/billets/{$organization->slug}/{$event->slug}/{$order->reservation_key}/statut");

    $waiting = $this->get("/billets/{$organization->slug}/{$event->slug}/{$order->reservation_key}/statut");
    $waiting->assertOk();
    $waiting->assertSee('en cours de confirmation');
});

it('propose le paiement par carte et redirige vers Stripe Checkout', function (): void {
    ['organization' => $organization, 'event' => $event, 'ticketType' => $ticketType] = makeGuestTicketedEvent();

    $this->post("/billets/{$organization->slug}/{$event->slug}", [
        'checkout_token' => (string) Str::uuid(),
        'buyer_name' => 'Alice',
        'buyer_email' => 'alice@example.com',
        'items' => [$ticketType->id => 1],
    ]);

    app(CurrentOrganization::class)->set($organization);
    $order = Order::query()->where('buyer_email', 'alice@example.com')->firstOrFail();
    app(CurrentOrganization::class)->clear();

    $fake = new class implements CardCheckoutProvider
    {
        public function createCheckoutSession(int $orderId, Money $amount, string $successUrl, string $cancelUrl): string
        {
            return 'https://checkout.stripe.com/pay/cs_test_fake';
        }
    };
    $this->app->bind(CardCheckoutProvider::class, fn () => $fake);

    $response = $this->post("/billets/{$organization->slug}/{$event->slug}/{$order->reservation_key}/paiement/carte");

    $response->assertRedirect('https://checkout.stripe.com/pay/cs_test_fake');
});

it('permet de télécharger le PDF d\'un billet une fois la commande payée', function (): void {
    config(['services.ticket_qr.secret' => 'test-qr-secret-au-moins-256-bits-pour-hs256']);
    ['organization' => $organization, 'event' => $event, 'ticketType' => $ticketType] = makeGuestTicketedEvent();

    $this->post("/billets/{$organization->slug}/{$event->slug}", [
        'checkout_token' => (string) Str::uuid(),
        'buyer_name' => 'Alice',
        'buyer_email' => 'alice@example.com',
        'items' => [$ticketType->id => 1],
    ]);

    app(CurrentOrganization::class)->set($organization);
    $order = Order::query()->where('buyer_email', 'alice@example.com')->firstOrFail();
    $paid = app(MarkOrderPaid::class)->handle($order, 'stripe', (string) Str::uuid(), $order->total);
    $ticket = $paid->items->first()->tickets->first();
    app(CurrentOrganization::class)->clear();

    $response = $this->get("/billets/{$organization->slug}/{$event->slug}/{$order->reservation_key}/billet/{$ticket->id}");

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});
