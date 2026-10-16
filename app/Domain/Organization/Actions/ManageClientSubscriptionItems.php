<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\PlanTier;
use App\Domain\Organization\StripeNotConfiguredException;
use LogicException;
use Stripe\StripeClient;

/**
 * Facturation consolidée du portefeuille d'une agence (D10, décision prise
 * avec l'utilisateur) : l'agence règle un seul abonnement Stripe, qui porte
 * à la fois son propre palier et un article par compte client qu'elle gère
 * — jamais de deuxième abonnement ni de paiement direct du client auprès
 * d'Itaza. Ce que l'agence facture à son client (organizations.resale_price_minor)
 * est un montant distinct, encaissé hors Itaza (voir sa colonne).
 *
 * organizations.stripe_subscription_item_id retient, pour CE compte,
 * l'identifiant de l'article qui le représente dans l'abonnement de
 * l'agence qui le gère — jamais le sien propre, un compte client n'a pas
 * d'abonnement Stripe à lui.
 */
final class ManageClientSubscriptionItems
{
    public function syncForPlanChange(Organization $agency, Organization $client, PlanTier $newPlan): void
    {
        if ($newPlan === PlanTier::Free) {
            $this->remove($client);

            return;
        }

        if ($agency->stripe_subscription_id === null) {
            throw new LogicException("L'agence #{$agency->id} n'a pas d'abonnement Stripe actif : impossible de facturer un compte client sur un palier payant tant qu'elle n'en a pas souscrit un, même pour son propre usage.");
        }

        $priceId = config("plans.{$newPlan->value}.stripe_price");

        if ($priceId === null || $priceId === '') {
            throw StripeNotConfiguredException::forPlan($newPlan->value);
        }

        $stripeClient = new StripeClient(config('services.stripe.secret'));

        if ($client->stripe_subscription_item_id === null) {
            $item = $stripeClient->subscriptionItems->create([
                'subscription' => $agency->stripe_subscription_id,
                'price' => $priceId,
                'proration_behavior' => 'create_prorations',
            ]);

            $client->update(['stripe_subscription_item_id' => $item->id]);

            return;
        }

        $stripeClient->subscriptionItems->update($client->stripe_subscription_item_id, [
            'price' => $priceId,
            'proration_behavior' => 'create_prorations',
        ]);
    }

    /**
     * Compte rendu à son client (ReleaseClientAccount) ou repassé gratuit :
     * l'article quitte l'abonnement de l'agence, elle ne le paie plus.
     */
    public function remove(Organization $client): void
    {
        if ($client->stripe_subscription_item_id === null) {
            return;
        }

        $stripeClient = new StripeClient(config('services.stripe.secret'));
        $stripeClient->subscriptionItems->delete($client->stripe_subscription_item_id, ['proration_behavior' => 'create_prorations']);

        $client->update(['stripe_subscription_item_id' => null]);
    }
}
