<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Actions\CreateOrder;
use App\Domain\Ticketing\Models\Payment;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Support\Capacity\Actions\GetRemainingCapacity;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Stripe\WebhookSignature;

/**
 * Signature générée par le SDK Stripe lui-même (WebhookSignature::generateSignatureHeader,
 * prévu par Stripe pour les tests) — un calcul HMAC local, sans appel
 * réseau. Pas besoin d'un vrai compte Stripe pour ce test (voir décision
 * prise avec l'utilisateur, T-052 : mocks/doubles en attendant de vraies
 * clés de test).
 */
function postSignedStripeWebhook(string $payload): TestResponse
{
    $secret = 'whsec_test';
    config(['services.stripe.webhook_secret' => $secret]);

    $header = WebhookSignature::generateSignatureHeader($payload, $secret);

    return test()->call('POST', route('webhooks.stripe'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => $header,
    ], $payload);
}

/**
 * @param  array<string, mixed>  $object
 */
function stripeEventPayload(string $type, string $eventId, array $object): string
{
    return json_encode([
        'id' => $eventId,
        'object' => 'event',
        'type' => $type,
        'data' => ['object' => $object],
    ]);
}

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($this->organization);
    $event = Event::factory()->for($this->organization)->create();
    $this->ticketType = TicketType::factory()->for($this->organization)->create(['event_id' => $event->id]);
    $this->tier = PriceTier::factory()->for($this->ticketType)->for($this->organization)->limited(3)->create();

    $this->order = app(CreateOrder::class)->handle(
        $this->organization->id, $event->id,
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        [['ticket_type_id' => $this->ticketType->id, 'quantity' => 1]],
        (string) Str::uuid(),
    );
});

it('refuse une requête sans signature Stripe valide', function (): void {
    config(['services.stripe.webhook_secret' => 'whsec_test']);

    $payload = stripeEventPayload('checkout.session.completed', 'evt_1', [
        'id' => 'cs_test_1', 'payment_intent' => 'pi_1', 'amount_total' => 2000, 'currency' => 'eur',
        'metadata' => ['order_id' => (string) $this->order->id],
    ]);

    test()->withHeader('Stripe-Signature', 't=1,v1=signature-invalide')->post(route('webhooks.stripe'), json_decode($payload, true))
        ->assertForbidden();
});

it('confirme le paiement et émet les billets sur checkout.session.completed', function (): void {
    $payload = stripeEventPayload('checkout.session.completed', 'evt_paid_1', [
        'id' => 'cs_test_1',
        'payment_intent' => 'pi_1',
        'amount_total' => $this->order->total->amountMinor(),
        'currency' => mb_strtolower($this->order->total->currency()),
        'metadata' => ['order_id' => (string) $this->order->id],
    ]);

    postSignedStripeWebhook($payload)->assertOk();

    app(CurrentOrganization::class)->set($this->organization);
    expect($this->order->fresh()->status->value)->toBe('paid');
    expect($this->order->fresh()->items->first()->tickets)->toHaveCount(1);
});

it('traite un webhook rejoué 3 fois de façon idempotente : une seule confirmation', function (): void {
    $payload = stripeEventPayload('checkout.session.completed', 'evt_replay', [
        'id' => 'cs_test_1',
        'payment_intent' => 'pi_replay',
        'amount_total' => $this->order->total->amountMinor(),
        'currency' => mb_strtolower($this->order->total->currency()),
        'metadata' => ['order_id' => (string) $this->order->id],
    ]);

    postSignedStripeWebhook($payload)->assertOk();
    postSignedStripeWebhook($payload)->assertOk();
    postSignedStripeWebhook($payload)->assertOk();

    app(CurrentOrganization::class)->set($this->organization);
    expect(Payment::query()->count())->toBe(1);
    expect($this->order->fresh()->items->first()->tickets)->toHaveCount(1);
});

it('échoue le paiement et libère le stock sur payment_intent.payment_failed', function (): void {
    $payload = stripeEventPayload('payment_intent.payment_failed', 'evt_failed_1', [
        'id' => 'pi_failed_1',
        'amount' => $this->order->total->amountMinor(),
        'currency' => mb_strtolower($this->order->total->currency()),
        'metadata' => ['order_id' => (string) $this->order->id],
        'last_payment_error' => ['message' => 'Votre carte a été refusée.'],
    ]);

    postSignedStripeWebhook($payload)->assertOk();

    app(CurrentOrganization::class)->set($this->organization);
    expect($this->order->fresh()->status->value)->toBe('failed');
    expect($this->order->fresh()->payments->first()->failure_reason)->toBe('Votre carte a été refusée.');

    $remaining = app(GetRemainingCapacity::class)->handle('price_tier', (string) $this->tier->id, $this->tier->quantity);
    expect($remaining)->toBe(3);
});

it('ignore silencieusement un webhook sans order_id en métadonnée', function (): void {
    $payload = stripeEventPayload('checkout.session.completed', 'evt_no_meta', [
        'id' => 'cs_test_x', 'payment_intent' => 'pi_x', 'amount_total' => 1000, 'currency' => 'eur', 'metadata' => [],
    ]);

    postSignedStripeWebhook($payload)->assertOk();

    app(CurrentOrganization::class)->set($this->organization);
    expect($this->order->fresh()->status->value)->toBe('pending');
});
