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
 *
 * Une agence (D10, facturation consolidée) porte, sur ce même abonnement,
 * un article par compte client qu'elle gère (voir
 * ManageClientSubscriptionItems) : passer l'agence elle-même à Free ne doit
 * annuler que son propre article, jamais tout l'abonnement — ses clients en
 * dépendent. L'abonnement n'est résilié en entier que s'il ne porte plus
 * aucun article client.
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
        $subscription = $client->subscriptions->retrieve($organization->stripe_subscription_id);
        $ownItemId = $organization->stripe_subscription_item_id ?? $subscription->items->data[0]->id;

        if ($newPlan === PlanTier::Free) {
            if ($this->hasManagedClientItems($organization)) {
                $client->subscriptionItems->delete($ownItemId, ['proration_behavior' => 'create_prorations']);

                $organization->update([
                    'plan' => PlanTier::Free,
                    'stripe_subscription_item_id' => null,
                    'dunning_stage' => 0,
                    'payment_failed_at' => null,
                ]);

                return $organization;
            }

            $client->subscriptions->cancel($organization->stripe_subscription_id);

            $organization->update([
                'plan' => PlanTier::Free,
                'stripe_subscription_id' => null,
                'stripe_subscription_item_id' => null,
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

        $client->subscriptionItems->update($ownItemId, [
            'price' => $priceId,
            'proration_behavior' => 'create_prorations',
        ]);

        $organization->update(['plan' => $newPlan, 'stripe_subscription_item_id' => $ownItemId]);

        return $organization;
    }

    private function hasManagedClientItems(Organization $organization): bool
    {
        return Organization::query()
            ->where('managed_by_organization_id', $organization->id)
            ->whereNotNull('stripe_subscription_item_id')
            ->exists();
    }
}
