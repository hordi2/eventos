<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Data;

use App\Support\Money;

/**
 * Don promis, prêt à devenir une commande (CreateDonationOrder).
 * registrationId reste un simple entier : Ticketing ne dépend d'aucun
 * modèle de Domain/Form.
 */
final class DonationPledge
{
    /**
     * @param  array<string, string>  $address  parties renseignées de l'adresse (line1, city…)
     */
    public function __construct(
        public readonly ?int $registrationId,
        public readonly Money $amount,
        public readonly ?string $cause,
        public readonly string $donorName,
        public readonly string $email,
        public readonly ?string $phone = null,
        public readonly ?string $company = null,
        public readonly array $address = [],
        public readonly bool $isAnonymous = false,
    ) {}
}
