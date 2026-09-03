<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Actions\CreateOrder;
use App\Domain\Ticketing\Actions\InitiateMobileMoneyPayment;
use App\Domain\Ticketing\Models\Payment;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Support\Capacity\Actions\GetRemainingCapacity;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Signature calculée exactement comme Flutterwave le documente : HMAC-SHA256
 * du corps brut, encodé en base64 — un calcul local, sans appel réseau, pas
 * besoin d'un vrai compte Flutterwave pour ce test (même décision que pour
 * Stripe, T-052).
 */
function postSignedFlutterwaveWebhook(string $payload): TestResponse
{
    $secret = 'whsec_flutterwave_test';
    config(['services.flutterwave.webhook_secret' => $secret]);

    $signature = base64_encode(hash_hmac('sha256', $payload, $secret, true));

    return test()->call('POST', route('webhooks.flutterwave'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_FLUTTERWAVE_SIGNATURE' => $signature,
    ], $payload);
}

function flutterwaveEventPayload(string $eventId, string $chargeId, string $status): string
{
    return json_encode([
        'id' => $eventId,
        'type' => 'charge.completed',
        'data' => ['id' => $chargeId, 'status' => $status, 'amount' => 2000, 'currency' => 'XOF'],
    ]);
}

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

it('refuse une requête sans signature Flutterwave valide', function (): void {
    config(['services.flutterwave.webhook_secret' => 'whsec_flutterwave_test']);

    test()->withHeader('flutterwave-signature', 'signature-invalide')
        ->post(route('webhooks.flutterwave'), ['id' => 'evt_1'])
        ->assertForbidden();
});

it('confirme le paiement et émet les billets sur un statut succeeded', function (): void {
    $payload = flutterwaveEventPayload('evt_paid_1', 'chg_1', 'succeeded');

    postSignedFlutterwaveWebhook($payload)->assertOk();

    app(CurrentOrganization::class)->set($this->organization);
    expect($this->order->fresh()->status->value)->toBe('paid');
    expect($this->order->fresh()->items->first()->tickets)->toHaveCount(1);
});

it('traite un webhook rejoué 3 fois de façon idempotente : une seule confirmation', function (): void {
    $payload = flutterwaveEventPayload('evt_replay', 'chg_1', 'succeeded');

    postSignedFlutterwaveWebhook($payload)->assertOk();
    postSignedFlutterwaveWebhook($payload)->assertOk();
    postSignedFlutterwaveWebhook($payload)->assertOk();

    app(CurrentOrganization::class)->set($this->organization);
    expect(Payment::query()->count())->toBe(1);
    expect($this->order->fresh()->items->first()->tickets)->toHaveCount(1);
});

it('échoue le paiement et libère le stock sur un statut failed', function (): void {
    $payload = flutterwaveEventPayload('evt_failed_1', 'chg_1', 'failed');

    postSignedFlutterwaveWebhook($payload)->assertOk();

    app(CurrentOrganization::class)->set($this->organization);
    expect($this->order->fresh()->status->value)->toBe('failed');

    $remaining = app(GetRemainingCapacity::class)->handle('price_tier', (string) $this->tier->id, $this->tier->quantity);
    expect($remaining)->toBe(3);
});

it('ignore silencieusement un webhook pour une charge inconnue', function (): void {
    $payload = flutterwaveEventPayload('evt_unknown', 'chg_inconnue', 'succeeded');

    postSignedFlutterwaveWebhook($payload)->assertOk();

    app(CurrentOrganization::class)->set($this->organization);
    expect($this->order->fresh()->status->value)->toBe('pending');
});
