<?php

declare(strict_types=1);

namespace App\Support\Payments;

use RuntimeException;

/**
 * Déclenche le mode dégradé (T-053, annexe C du CDC : « Fiabilité des
 * agrégateurs Mobile Money ») — voir InitiateMobileMoneyPayment, qui la
 * capture pour basculer automatiquement sur le paiement à l'arrivée
 * (ChooseOnSitePayment) plutôt que de faire échouer la commande.
 */
final class MobileMoneyProviderUnavailableException extends RuntimeException
{
    public static function forProvider(string $provider): self
    {
        return new self("Le prestataire Mobile Money \"{$provider}\" est indisponible.");
    }
}
