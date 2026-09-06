<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\PlanTier;
use App\Domain\Organization\StripeNotConfiguredException;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Stripe\StripeClient;

/**
 * Abonnement Stripe (mode "subscription") : distinct de StripeCheckoutProvider
 * (T-052), qui crée des sessions "payment" ponctuelles pour la billetterie —
 * les deux flux Stripe ne partagent aucune session ni webhook.
 */
final class CreateBillingCheckoutSession
{
    public function handle(Organization $organization, PlanTier $plan, string $successUrl, string $cancelUrl, User $user): string
    {
        Gate::forUser($user)->authorize('manageBilling', $organization);

        $priceId = config("plans.{$plan->value}.stripe_price");

        if ($priceId === null || $priceId === '') {
            throw StripeNotConfiguredException::forPlan($plan->value);
        }

        $client = new StripeClient(config('services.stripe.secret'));

        $session = $client->checkout->sessions->create([
            'mode' => 'subscription',
            'payment_method_types' => ['card'],
            'line_items' => [['price' => $priceId, 'quantity' => 1]],
            'client_reference_id' => (string) $organization->id,
            'customer' => $organization->stripe_customer_id,
            'customer_email' => $organization->stripe_customer_id === null ? $user->email : null,
            'metadata' => ['organization_id' => (string) $organization->id, 'plan' => $plan->value],
            'subscription_data' => [
                'metadata' => ['organization_id' => (string) $organization->id, 'plan' => $plan->value],
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);

        return (string) $session->url;
    }
}
