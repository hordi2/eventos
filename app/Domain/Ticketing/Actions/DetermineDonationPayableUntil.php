<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use Carbon\CarbonImmutable;

/**
 * Échéance de paiement d'un don promis (T-056) : aucune place à tenir,
 * donc jusqu'à deux jours après l'événement, le temps d'un règlement
 * tardif, et jamais moins de deux jours à partir de maintenant. Partagée
 * par l'ouverture de la commande et sa réouverture après un échec
 * (RetryOrderPayment), pour que les deux appliquent la même règle.
 */
final class DetermineDonationPayableUntil
{
    public function handle(CarbonImmutable $eventEndsAt): CarbonImmutable
    {
        return ($eventEndsAt->isPast() ? CarbonImmutable::now() : $eventEndsAt)->addDays(2);
    }
}
