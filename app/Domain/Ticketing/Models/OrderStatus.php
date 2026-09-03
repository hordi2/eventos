<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Models;

/**
 * Machine à états M5.4 : pending -> paid | failed | expired | refunded.
 * Refunded n'est atteignable que depuis Paid ; failed/expired uniquement
 * depuis Pending (voir RefundOrder / MarkOrderPaid / FailOrderPayment /
 * ExpireOrder, qui gardent chacun leur transition).
 *
 * PaymentOnSite (D3, T-054) est une branche parallèle à pending : l'invité
 * a choisi de payer à l'arrivée plutôt qu'en ligne, donc pas de réservation
 * à 15 minutes (ChooseOnSitePayment efface reserved_until) — la commande
 * reste dans cet état jusqu'au check-in, où MarkOrderPaid l'accepte comme
 * pending comme point de départ valide vers paid.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case PaymentOnSite = 'payment_on_site';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case Refunded = 'refunded';
}
