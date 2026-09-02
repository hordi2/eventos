<?php

declare(strict_types=1);

namespace App\Domain\Form\Support;

use App\Domain\Form\Data\EventRegistrationContext;
use Carbon\CarbonImmutable;

/**
 * Utilisé à la fois par SubmitRegistration (la seule source de vérité, à
 * l'instant précis de la soumission) et par le contrôleur invité (T-031,
 * pour éviter de laisser l'invité remplir tout le formulaire avant de
 * découvrir que les inscriptions sont fermées) — une seule implémentation,
 * jamais deux versions qui pourraient diverger.
 */
final class IsRegistrationWindowOpen
{
    public function handle(EventRegistrationContext $context): bool
    {
        $now = CarbonImmutable::now($context->timezone);

        $opensAt = $context->registrationOpensAt?->setTimezone($context->timezone);
        $closesAt = $context->registrationClosesAt?->setTimezone($context->timezone);

        if ($opensAt !== null && $now->lessThan($opensAt)) {
            return false;
        }

        if ($closesAt !== null && $now->greaterThan($closesAt)) {
            return false;
        }

        return true;
    }
}
