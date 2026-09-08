<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\PlanTier;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Stripe\WebhookSignature;

/**
 * Même mécanique que StripeWebhookControllerTest (T-052, billetterie) :
 * signature générée par le SDK Stripe lui-même, aucun appel réseau
 * nécessaire. Secret de signature distinct (billing_webhook_secret).
 */
function postSignedStripeBillingWebhook(string $payload): TestResponse
{
    $secret = 'whsec_billing_test';
    config(['services.stripe.billing_webhook_secret' => $secret]);

    $header = WebhookSignature::generateSignatureHeader($payload, $secret);

    return test()->call('POST', route('webhooks.stripe.billing'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => $header,
    ], $payload);
}

/**
 * @param  array<string, mixed>  $object
 */
function stripeBillingEventPayload(string $type, string $eventId, array $object): string
{
    return json_encode([
        'id' => $eventId,
        'object' => 'event',
        'type' => $type,
        'data' => ['object' => $object],
    ]);
}

it('refuse une requête sans signature Stripe valide', function (): void {
    config(['services.stripe.billing_webhook_secret' => 'whsec_billing_test']);

    $payload = stripeBillingEventPayload('customer.subscription.updated', 'evt_1', ['customer' => 'cus_1', 'status' => 'active']);

    test()->withHeader('Stripe-Signature', 't=1,v1=signature-invalide')->post(route('webhooks.stripe.billing'), json_decode($payload, true))
        ->assertForbidden();
});

it('active le plan et enregistre l\'abonnement sur checkout.session.completed', function (): void {
    $organization = Organization::factory()->create();

    $payload = stripeBillingEventPayload('checkout.session.completed', 'evt_checkout_1', [
        'mode' => 'subscription',
        'customer' => 'cus_test_1',
        'subscription' => 'sub_test_1',
        'client_reference_id' => (string) $organization->id,
        'metadata' => ['organization_id' => (string) $organization->id, 'plan' => 'personal_essential'],
    ]);

    postSignedStripeBillingWebhook($payload)->assertOk();

    $fresh = Organization::query()->withoutGlobalScopes()->findOrFail($organization->id);
    expect($fresh->plan)->toBe(PlanTier::PersonalEssential);
    expect($fresh->stripe_customer_id)->toBe('cus_test_1');
    expect($fresh->stripe_subscription_id)->toBe('sub_test_1');
    expect($fresh->subscription_status)->toBe('active');
});

it('prolonge de 30 jours l\'abonnement du parrain et du filleul au premier paiement confirmé (Refer-a-Friend)', function (): void {
    $referrer = Organization::factory()->create([
        'referral_code' => 'ami-itaza',
        'stripe_subscription_id' => 'sub_referrer',
        'subscription_current_period_end' => now()->addDays(10),
    ]);
    $referred = Organization::factory()->create(['referred_by_organization_id' => $referrer->id]);

    $payload = stripeBillingEventPayload('checkout.session.completed', 'evt_referral_1', [
        'mode' => 'subscription',
        'customer' => 'cus_referred',
        'subscription' => 'sub_referred',
        'client_reference_id' => (string) $referred->id,
        'metadata' => ['organization_id' => (string) $referred->id, 'plan' => 'personal_essential'],
    ]);

    postSignedStripeBillingWebhook($payload)->assertOk();

    $freshReferred = Organization::query()->withoutGlobalScopes()->findOrFail($referred->id);
    $freshReferrer = Organization::query()->withoutGlobalScopes()->findOrFail($referrer->id);

    expect($freshReferred->referral_rewarded_at)->not->toBeNull();
    expect($freshReferred->subscription_current_period_end->diffInDays(now(), true))->toBeGreaterThan(28);
    expect($freshReferrer->subscription_current_period_end->format('Y-m-d'))->toBe(now()->addDays(40)->format('Y-m-d'));
});

it('ne récompense pas deux fois le même parrainage si le webhook est rejoué', function (): void {
    $referrer = Organization::factory()->create(['referral_code' => 'ami-itaza-2', 'stripe_subscription_id' => 'sub_referrer_2']);
    $referred = Organization::factory()->create([
        'referred_by_organization_id' => $referrer->id,
        'referral_rewarded_at' => now()->subDay(),
        'stripe_subscription_id' => 'sub_referred_2',
        'subscription_current_period_end' => now()->addDays(5),
    ]);

    $payload = stripeBillingEventPayload('checkout.session.completed', 'evt_referral_2', [
        'mode' => 'subscription',
        'customer' => 'cus_referred_2',
        'subscription' => 'sub_referred_2',
        'client_reference_id' => (string) $referred->id,
        'metadata' => ['organization_id' => (string) $referred->id, 'plan' => 'personal_essential'],
    ]);

    postSignedStripeBillingWebhook($payload)->assertOk();

    $fresh = Organization::query()->withoutGlobalScopes()->findOrFail($referred->id);
    expect($fresh->subscription_current_period_end->format('Y-m-d'))->toBe(now()->addDays(5)->format('Y-m-d'));
});

it('repasse l\'organisation au plan gratuit sur customer.subscription.deleted', function (): void {
    $organization = Organization::factory()->create([
        'plan' => PlanTier::PersonalEssential,
        'stripe_customer_id' => 'cus_test_2',
        'stripe_subscription_id' => 'sub_test_2',
        'subscription_status' => 'active',
    ]);

    $payload = stripeBillingEventPayload('customer.subscription.deleted', 'evt_deleted_1', ['customer' => 'cus_test_2']);

    postSignedStripeBillingWebhook($payload)->assertOk();

    $fresh = Organization::query()->withoutGlobalScopes()->findOrFail($organization->id);
    expect($fresh->plan)->toBe(PlanTier::Free);
    expect($fresh->stripe_subscription_id)->toBeNull();
});

it('marque l\'abonnement en échec de paiement sur invoice.payment_failed, sans écraser la date du premier échec', function (): void {
    $organization = Organization::factory()->create([
        'plan' => PlanTier::PersonalEssential,
        'stripe_customer_id' => 'cus_test_3',
        'stripe_subscription_id' => 'sub_test_3',
        'subscription_status' => 'active',
    ]);

    $payload = stripeBillingEventPayload('invoice.payment_failed', 'evt_failed_1', ['customer' => 'cus_test_3']);
    postSignedStripeBillingWebhook($payload)->assertOk();

    $afterFirst = Organization::query()->withoutGlobalScopes()->findOrFail($organization->id);
    expect($afterFirst->subscription_status)->toBe('past_due');
    $firstFailureAt = $afterFirst->payment_failed_at;
    expect($firstFailureAt)->not->toBeNull();

    // Un deuxième échec (nouvelle tentative Stripe) ne doit pas repousser la
    // date du premier échec — ProcessDunning compte les jours depuis celle-ci.
    $payload2 = stripeBillingEventPayload('invoice.payment_failed', 'evt_failed_2', ['customer' => 'cus_test_3']);
    postSignedStripeBillingWebhook($payload2)->assertOk();

    $afterSecond = Organization::query()->withoutGlobalScopes()->findOrFail($organization->id);
    expect($afterSecond->payment_failed_at->equalTo($firstFailureAt))->toBeTrue();
});

it('régularise l\'abonnement sur invoice.payment_succeeded', function (): void {
    $organization = Organization::factory()->create([
        'plan' => PlanTier::PersonalEssential,
        'stripe_customer_id' => 'cus_test_4',
        'stripe_subscription_id' => 'sub_test_4',
        'subscription_status' => 'past_due',
        'payment_failed_at' => now()->subDays(2),
        'dunning_stage' => 1,
    ]);

    $payload = stripeBillingEventPayload('invoice.payment_succeeded', 'evt_succeeded_1', ['customer' => 'cus_test_4']);
    postSignedStripeBillingWebhook($payload)->assertOk();

    $fresh = Organization::query()->withoutGlobalScopes()->findOrFail($organization->id);
    expect($fresh->subscription_status)->toBe('active');
    expect($fresh->dunning_stage)->toBe(0);
    expect($fresh->payment_failed_at)->toBeNull();
});

it('traite un webhook rejoué 3 fois de façon idempotente : une seule ligne journalisée', function (): void {
    $organization = Organization::factory()->create([
        'plan' => PlanTier::PersonalEssential,
        'stripe_customer_id' => 'cus_test_5',
        'subscription_status' => 'active',
    ]);

    $payload = stripeBillingEventPayload('customer.subscription.deleted', 'evt_replay_1', ['customer' => 'cus_test_5']);

    postSignedStripeBillingWebhook($payload)->assertOk();
    postSignedStripeBillingWebhook($payload)->assertOk();
    postSignedStripeBillingWebhook($payload)->assertOk();

    expect(DB::table('stripe_webhook_events')->where('event_id', 'evt_replay_1')->count())->toBe(1);

    $fresh = Organization::query()->withoutGlobalScopes()->findOrFail($organization->id);
    expect($fresh->plan)->toBe(PlanTier::Free);
});
