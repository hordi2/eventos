<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\PlanTier;
use App\Domain\Organization\StripeNotConfiguredException;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Stripe\StripeClient;

/**
 * Changement de plan pour une organisation déjà abonnée (AC T-074 :
 * « changement de plan avec prorata correct ») : Stripe calcule lui-même
 * le prorata (proration_behavior: create_prorations, comportement par
 * défaut de l'API Subscriptions) — pas de calcul de prorata réimplémenté
 * ici, ce serait dupliquer une logique financière d'un tiers de confiance.
 * Le passage à Free annule l'abonnement plutôt que de le maintenir à 0 :
 * Stripe ne modélise pas un "plan gratuit" comme un prix à zéro.
 */
final class ChangeOrganizationPlan
{
    public function handle(Organization $organization, PlanTier $newPlan, User $user): Organization
    {
        Gate::forUser($user)->authorize('manageBilling', $organization);

        if ($organization->stripe_subscription_id === null) {
            throw new LogicException("L'organisation #{$organization->id} n'a pas d'abonnement Stripe actif à modifier.");
        }

        $client = new StripeClient(config('services.stripe.secret'));

        if ($newPlan === PlanTier::Free) {
            $client->subscriptions->cancel($organization->stripe_subscription_id);

            $organization->update([
                'plan' => PlanTier::Free,
                'stripe_subscription_id' => null,
                'subscription_status' => null,
                'subscription_current_period_end' => null,
                'dunning_stage' => 0,
                'payment_failed_at' => null,
            ]);

            return $organization;
        }

        $priceId = config("plans.{$newPlan->value}.stripe_price");

        if ($priceId === null || $priceId === '') {
            throw StripeNotConfiguredException::forPlan($newPlan->value);
        }

        $subscription = $client->subscriptions->retrieve($organization->stripe_subscription_id);

        $client->subscriptions->update($organization->stripe_subscription_id, [
            'items' => [['id' => $subscription->items->data[0]->id, 'price' => $priceId]],
            'proration_behavior' => 'create_prorations',
        ]);

        $organization->update(['plan' => $newPlan]);

        return $organization;
    }
}
