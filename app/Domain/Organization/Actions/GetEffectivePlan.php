<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\PlanTier;

/**
 * Le plan réellement appliqué pour le calcul des quotas — jamais forcément
 * `organization.plan` (AC T-074 : « échec de prélèvement... puis
 * restriction »). La restriction est un calcul à la volée : elle ne touche
 * jamais la colonne `plan` elle-même, l'organisation retrouve son plan payé
 * dès que le paiement est régularisé (webhook invoice.payment_succeeded),
 * sans reconfiguration ni perte de données.
 */
final class GetEffectivePlan
{
    public function handle(Organization $organization): PlanTier
    {
        if ($organization->plan !== PlanTier::Free && $organization->subscription_status === 'past_due' && $organization->dunning_stage >= 3) {
            return PlanTier::Free;
        }

        return $organization->plan;
    }
}
