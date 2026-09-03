<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Models;

/**
 * Machine à états M5.4 : pending -> paid | failed | expired | refunded.
 * Refunded n'est atteignable que depuis Paid ; les trois autres uniquement
 * depuis Pending (voir RefundOrder / MarkOrderPaid / FailOrderPayment /
 * ExpireOrder, qui gardent chacun leur transition).
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case Refunded = 'refunded';
}
