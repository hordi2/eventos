<?php

declare(strict_types=1);

namespace App\Domain\Organization;

use RuntimeException;

/**
 * Levée quand un Price ID Stripe n'a pas encore été configuré pour un plan
 * payant (voir config/plans.php) — un échec propre et explicite plutôt
 * qu'une erreur d'API Stripe cryptique en attendant que le tableau de bord
 * Stripe soit configuré (T-074, choisi explicitement avec l'utilisateur).
 */
final class StripeNotConfiguredException extends RuntimeException
{
    public static function forPlan(string $plan): self
    {
        return new self("Le plan « {$plan} » n'a pas encore de Price ID Stripe configuré (voir .env : STRIPE_PRICE_".mb_strtoupper($plan).').');
    }
}
