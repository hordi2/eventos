<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\PlanTier;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Payments\InvalidWebhookSignatureException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Event as StripeEvent;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Endpoint webhook séparé de RecordStripeWebhookEvent (T-052, billetterie) :
 * les événements d'abonnement (customer.subscription.*, invoice.*) n'ont
 * aucun order_id en métadonnée, ils se retrouvent par stripe_customer_id —
 * une structure trop différente pour partager la même méthode apply(), même
 * si les deux partagent la table stripe_webhook_events pour l'idempotence
 * (§4.4 du CLAUDE.md) : l'event_id Stripe est unique tous endpoints confondus.
 */
final class RecordStripeBillingWebhookEvent
{
    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
    ) {}

    public function handle(string $payload, string $signatureHeader): void
    {
        $event = $this->verifySignature($payload, $signatureHeader);

        $inserted = DB::table('stripe_webhook_events')->insertOrIgnore([[
            'provider' => 'stripe',
            'event_id' => $event->id,
            'payload' => $payload,
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]]);

        if ($inserted === 0) {
            Log::info("Webhook Stripe (facturation) déjà traité, ignoré : {$event->id}.");

            return;
        }

        $this->apply($event);
    }

    private function verifySignature(string $payload, string $signatureHeader): StripeEvent
    {
        $webhookSecret = config('services.stripe.billing_webhook_secret');

        if ($webhookSecret === null) {
            throw InvalidWebhookSignatureException::forProvider('stripe');
        }

        try {
            return Webhook::constructEvent($payload, $signatureHeader, $webhookSecret);
        } catch (SignatureVerificationException) {
            throw InvalidWebhookSignatureException::forProvider('stripe');
        }
    }

    private function apply(StripeEvent $event): void
    {
        $object = $event->data->object;

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($object),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($object),
            'invoice.payment_failed' => $this->handlePaymentFailed($object),
            'invoice.payment_succeeded' => $this->handlePaymentSucceeded($object),
            default => Log::info("Événement Stripe (facturation) ignoré : {$event->type}."),
        };
    }

    private function handleCheckoutCompleted(object $object): void
    {
        if (($object->mode ?? null) !== 'subscription') {
            return;
        }

        $organizationId = (int) ($object->metadata->organization_id ?? $object->client_reference_id ?? 0);
        $plan = PlanTier::tryFrom((string) ($object->metadata->plan ?? ''));
        $organization = $this->findOrganization($organizationId);

        if ($organization === null || $plan === null) {
            Log::warning("Webhook Stripe (facturation) checkout.session.completed sans organisation/plan valide : org #{$organizationId}.");

            return;
        }

        $this->currentOrganization->set($organization->id);

        $organization->update([
            'plan' => $plan,
            'stripe_customer_id' => (string) $object->customer,
            'stripe_subscription_id' => (string) $object->subscription,
            'subscription_status' => 'active',
            'dunning_stage' => 0,
            'payment_failed_at' => null,
        ]);
    }

    private function handleSubscriptionUpdated(object $object): void
    {
        $organization = $this->findOrganizationByCustomer((string) $object->customer);

        if ($organization === null) {
            return;
        }

        $this->currentOrganization->set($organization->id);

        $attributes = [
            'subscription_status' => (string) $object->status,
            'subscription_current_period_end' => isset($object->current_period_end)
                ? now()->setTimestamp($object->current_period_end)
                : null,
        ];

        if ($object->status === 'active') {
            $attributes['dunning_stage'] = 0;
            $attributes['payment_failed_at'] = null;
        }

        $organization->update($attributes);
    }

    private function handleSubscriptionDeleted(object $object): void
    {
        $organization = $this->findOrganizationByCustomer((string) $object->customer);

        if ($organization === null) {
            return;
        }

        $this->currentOrganization->set($organization->id);

        $organization->update([
            'plan' => PlanTier::Free,
            'stripe_subscription_id' => null,
            'subscription_status' => null,
            'subscription_current_period_end' => null,
            'dunning_stage' => 0,
            'payment_failed_at' => null,
        ]);
    }

    private function handlePaymentFailed(object $object): void
    {
        $organization = $this->findOrganizationByCustomer((string) ($object->customer ?? ''));

        if ($organization === null) {
            return;
        }

        $this->currentOrganization->set($organization->id);

        // Ne touche payment_failed_at que s'il n'était pas déjà positionné :
        // ProcessDunning compte les jours depuis le PREMIER échec, un
        // deuxième webhook (nouvelle tentative Stripe) ne doit pas repousser
        // l'horloge des relances.
        if ($organization->payment_failed_at === null) {
            $organization->update(['payment_failed_at' => now()]);
        }

        $organization->update(['subscription_status' => 'past_due']);
    }

    private function handlePaymentSucceeded(object $object): void
    {
        $organization = $this->findOrganizationByCustomer((string) ($object->customer ?? ''));

        if ($organization === null) {
            return;
        }

        $this->currentOrganization->set($organization->id);

        $organization->update([
            'subscription_status' => 'active',
            'dunning_stage' => 0,
            'payment_failed_at' => null,
        ]);
    }

    private function findOrganization(int $id): ?Organization
    {
        if ($id === 0) {
            return null;
        }

        return Organization::query()->withoutGlobalScopes()->find($id);
    }

    private function findOrganizationByCustomer(string $customerId): ?Organization
    {
        if ($customerId === '') {
            return null;
        }

        $organization = Organization::query()->withoutGlobalScopes()->where('stripe_customer_id', $customerId)->first();

        if ($organization === null) {
            Log::warning("Webhook Stripe (facturation) reçu pour un client inconnu : {$customerId}.");
        }

        return $organization;
    }
}
