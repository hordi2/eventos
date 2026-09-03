<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Support\Money;

/**
 * Pays cible = Afrique francophone (RDC, Congo, Cameroun, Côte d'Ivoire,
 * Sénégal — §1 CLAUDE.md), où l'indicatif pays fait toujours 3 chiffres :
 * countryCode et phoneNumber sont donc fournis séparément par l'appelant
 * plutôt que reconstitués depuis un numéro E.164 (longueur d'indicatif
 * variable en général, pas dans ce périmètre).
 */
final class MobileMoneyChargeRequest
{
    public function __construct(
        public readonly int $orderId,
        public readonly Money $amount,
        public readonly string $countryCode,
        public readonly string $phoneNumber,
        public readonly string $network,
        public readonly string $buyerEmail,
        public readonly string $buyerFirstName,
        public readonly string $buyerLastName,
    ) {}
}
