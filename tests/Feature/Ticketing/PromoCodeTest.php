<?php

declare(strict_types=1);

use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Domain\Ticketing\Models\PromoCode;
use App\Domain\Ticketing\Models\PromoCodeKind;
use App\Models\User;
use App\Support\Money;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Code promo posé sur l'événement billetterie de référence (deux billets à
 * 20 €), avec un administrateur qui peut le gérer.
 *
 * @param  array<string, mixed>  $attributes
 * @return array{0: PromoCode, 1: User}
 */
function makePromoCode(array $setup, array $attributes = []): array
{
    app(CurrentOrganization::class)->set($setup['organization']);
    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $setup['organization']->id, 'role' => MembershipRole::Admin]);

    $promoCode = PromoCode::factory()->create([
        'organization_id' => $setup['organization']->id,
        'event_id' => $setup['event']->id,
        'created_by' => $admin->id,
        'code' => 'EARLY2026',
        ...$attributes,
    ]);
    app(CurrentOrganization::class)->clear();

    return [$promoCode, $admin];
}

/**
 * @param  array<string, mixed>  $extra
 */
function buyTickets(TestCase $test, array $setup, int $quantity, array $extra = []): void
{
    $test->post("/billets/{$setup['organization']->slug}/{$setup['event']->slug}", [
        'checkout_token' => (string) Str::uuid(),
        'buyer_name' => 'Alice Kouassi',
        'buyer_email' => 'alice@example.com',
        'items' => [$setup['ticketType']->id => $quantity],
        ...$extra,
    ]);
}

it('applique une réduction en pourcentage au total des billets', function (): void {
    $setup = makeGuestTicketedEvent();
    makePromoCode($setup, ['kind' => PromoCodeKind::Percent, 'percent_bp' => 2500]);

    buyTickets($this, $setup, 2, ['promo_code' => 'early2026']);

    app(CurrentOrganization::class)->set($setup['organization']);
    $order = Order::query()->sole();
    // 2 × 20 € = 40 €, moins 25 % = 30 €.
    expect($order->discount->amountMinor())->toBe(1000)
        ->and($order->total->amountMinor())->toBe(3000)
        ->and($order->promo_code_id)->not->toBeNull();
});

it('applique une réduction en montant, sans jamais descendre sous zéro ni remiser un don', function (): void {
    $setup = makeGuestTicketedEvent();
    makePromoCode($setup, ['kind' => PromoCodeKind::Amount, 'percent_bp' => null, 'amount' => Money::fromMinorUnits(5000, 'EUR')]);

    buyTickets($this, $setup, 1, ['promo_code' => 'EARLY2026', 'donation_amount' => '10']);

    app(CurrentOrganization::class)->set($setup['organization']);
    $order = Order::query()->sole();
    // Billet à 20 € : la réduction s'arrête à 20 €, et le don de 10 € reste dû.
    expect($order->discount->amountMinor())->toBe(2000)
        ->and($order->total->amountMinor())->toBe(1000);
});

it('refuse un code inconnu, clos ou déjà épuisé, sans créer de commande', function (): void {
    $setup = makeGuestTicketedEvent();
    $base = "/billets/{$setup['organization']->slug}/{$setup['event']->slug}";

    buyTickets($this, $setup, 1, ['promo_code' => 'INCONNU']);
    $this->get($base)->assertOk();
    $this->post($base, [
        'checkout_token' => (string) Str::uuid(),
        'buyer_name' => 'Alice',
        'buyer_email' => 'alice@example.com',
        'items' => [$setup['ticketType']->id => 1],
        'promo_code' => 'INCONNU',
    ])->assertSessionHasErrors('promo_code');

    makePromoCode($setup, ['ends_at' => CarbonImmutable::now()->subDay()]);
    $this->post($base, [
        'checkout_token' => (string) Str::uuid(),
        'buyer_name' => 'Alice',
        'buyer_email' => 'alice@example.com',
        'items' => [$setup['ticketType']->id => 1],
        'promo_code' => 'EARLY2026',
    ])->assertSessionHasErrors('promo_code');

    app(CurrentOrganization::class)->set($setup['organization']);
    expect(Order::query()->count())->toBe(0);
});

it('compte les utilisations et refuse le code une fois la limite atteinte', function (): void {
    $setup = makeGuestTicketedEvent();
    [$promoCode] = makePromoCode($setup, ['max_uses' => 1]);
    $base = "/billets/{$setup['organization']->slug}/{$setup['event']->slug}";

    buyTickets($this, $setup, 1, ['promo_code' => 'EARLY2026']);

    app(CurrentOrganization::class)->set($setup['organization']);
    expect(Order::query()->where('promo_code_id', $promoCode->id)->count())->toBe(1);
    app(CurrentOrganization::class)->clear();

    $this->post($base, [
        'checkout_token' => (string) Str::uuid(),
        'buyer_name' => 'Moussa',
        'buyer_email' => 'moussa@example.com',
        'items' => [$setup['ticketType']->id => 1],
        'promo_code' => 'EARLY2026',
    ])->assertSessionHasErrors('promo_code');

    // Une commande expirée rend le code à nouveau disponible.
    app(CurrentOrganization::class)->set($setup['organization']);
    Order::query()->sole()->update(['status' => OrderStatus::Expired]);
    app(CurrentOrganization::class)->clear();

    $this->post($base, [
        'checkout_token' => (string) Str::uuid(),
        'buyer_name' => 'Moussa',
        'buyer_email' => 'moussa@example.com',
        'items' => [$setup['ticketType']->id => 1],
        'promo_code' => 'EARLY2026',
    ])->assertSessionHasNoErrors();
});

it('gère les codes depuis la billetterie et refuse un doublon', function (): void {
    $setup = makeGuestTicketedEvent();
    [, $admin] = makePromoCode($setup, ['code' => 'DEJAPRIS', 'max_uses' => 5]);

    $this->actingAs($admin)->get("/events/{$setup['event']->id}/codes-promo")->assertInertia(fn ($page) => $page
        ->component('PromoCodes/Index')
        ->has('promoCodes', 1)
        ->where('promoCodes.0.code', 'DEJAPRIS')
        ->where('promoCodes.0.reduction', '10 %')
        ->where('promoCodes.0.uses', 0));

    $this->actingAs($admin)->post("/events/{$setup['event']->id}/codes-promo", [
        'code' => 'dejapris',
        'kind' => 'percent',
        'percent' => 20,
    ])->assertSessionHasErrors('code');

    $this->actingAs($admin)->post("/events/{$setup['event']->id}/codes-promo", [
        'code' => 'nouveau-15',
        'kind' => 'amount',
        'amount_minor' => 1500,
        'max_uses' => 30,
    ])->assertSessionHas('status', 'promo-code-saved');

    app(CurrentOrganization::class)->set($setup['organization']);
    $created = PromoCode::query()->where('code', 'NOUVEAU-15')->sole();
    expect($created->kind)->toBe(PromoCodeKind::Amount)
        ->and($created->amount->amountMinor())->toBe(1500)
        ->and($created->max_uses)->toBe(30);
});

it('réserve les codes promo aux membres qui gèrent la billetterie', function (): void {
    $setup = makeGuestTicketedEvent();
    [$promoCode] = makePromoCode($setup);

    app(CurrentOrganization::class)->set($setup['organization']);
    $viewer = User::factory()->create();
    $viewer->memberships()->create(['organization_id' => $setup['organization']->id, 'role' => MembershipRole::Viewer]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($viewer)->get("/events/{$setup['event']->id}/codes-promo")->assertForbidden();
    $this->actingAs($viewer)->delete("/promo-codes/{$promoCode->id}")->assertForbidden();
});
