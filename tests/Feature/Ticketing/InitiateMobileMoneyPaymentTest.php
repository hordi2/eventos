<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Actions\CreateOrder;
use App\Domain\Ticketing\Actions\InitiateMobileMoneyPayment;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config(['services.flutterwave.secret' => 'test-secret']);
    $this->organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($this->organization);
    $event = Event::factory()->for($this->organization)->create();
    $ticketType = TicketType::factory()->for($this->organization)->create(['event_id' => $event->id]);
    PriceTier::factory()->for($ticketType)->for($this->organization)->create(['name' => 'Normal']);

    $this->order = app(CreateOrder::class)->handle(
        $this->organization->id, $event->id,
        ['name' => 'Alice Kouassi', 'email' => 'alice@example.com'],
        [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
        (string) Str::uuid(),
    );
});

it('initie une charge Mobile Money et crée un paiement en attente', function (): void {
    Http::fake([
        '*/customers' => Http::response(['data' => ['id' => 'cus_123']], 200),
        '*/payment-methods' => Http::response(['data' => ['id' => 'pmd_123']], 200),
        '*/charges' => Http::response(['data' => ['id' => 'chg_123', 'status' => 'pending']], 200),
    ]);

    $updated = app(InitiateMobileMoneyPayment::class)->handle($this->order, '225', '0102030405', 'MTN');

    expect($updated->status->value)->toBe('pending');
    expect($updated->payments->first()->status->value)->toBe('pending');
    expect($updated->payments->first()->provider_payment_id)->toBe('chg_123');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/customers')
        && $request['name']['first'] === 'Alice'
        && $request['phone']['country_code'] === '225');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/payment-methods')
        && $request['mobile_money']['network'] === 'MTN');
});

it('bascule sur le paiement à l\'arrivée quand le prestataire est indisponible (mode dégradé)', function (): void {
    Http::fake(['*/customers' => Http::response(null, 500)]);

    $updated = app(InitiateMobileMoneyPayment::class)->handle($this->order, '225', '0102030405', 'MTN');

    expect($updated->status->value)->toBe('payment_on_site');
    expect($updated->reserved_until)->toBeNull();
    expect($updated->payments)->toHaveCount(0);
});
