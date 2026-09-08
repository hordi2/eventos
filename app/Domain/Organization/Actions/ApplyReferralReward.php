<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Organization;
use Illuminate\Support\Facades\DB;

/**
 * Refer-a-Friend (Paramètres → Refer-a-Friend, demande utilisateur) : au
 * premier paiement confirmé d'une organisation parrainée, un mois gratuit
 * est offert aux deux organisations — décision utilisateur explicite :
 * prolonger `subscription_current_period_end` de 30 jours plutôt qu'un
 * coupon Stripe (aucune configuration Stripe supplémentaire nécessaire).
 * `referral_rewarded_at` rend l'opération idempotente (règle 4.4 du
 * CLAUDE.md) : un même filleul ne peut déclencher la récompense qu'une
 * seule fois, même si le webhook Stripe est rejoué.
 */
final class ApplyReferralReward
{
    private const REWARD_DAYS = 30;

    public function handle(Organization $referredOrganization): void
    {
        if ($referredOrganization->referred_by_organization_id === null || $referredOrganization->referral_rewarded_at !== null) {
            return;
        }

        DB::transaction(function () use ($referredOrganization): void {
            $referrer = Organization::query()->find($referredOrganization->referred_by_organization_id);

            $referredOrganization->update(['referral_rewarded_at' => now()]);
            $this->extendSubscription($referredOrganization);

            if ($referrer !== null) {
                $this->extendSubscription($referrer);
            }
        });
    }

    private function extendSubscription(Organization $organization): void
    {
        // Rien à prolonger pour une organisation encore au plan gratuit,
        // sans abonnement Stripe actif — le mois offert ne s'applique
        // qu'aux organisations déjà payantes.
        if ($organization->stripe_subscription_id === null) {
            return;
        }

        $base = $organization->subscription_current_period_end ?? now();
        $organization->update(['subscription_current_period_end' => $base->addDays(self::REWARD_DAYS)]);
    }
}
