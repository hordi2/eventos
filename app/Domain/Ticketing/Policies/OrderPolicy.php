<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Policies;

use App\Domain\Ticketing\Models\Order;
use App\Models\User;

final class OrderPolicy
{
    public function refund(User $user, Order $order): bool
    {
        return $user->can('refundTickets', $order->organization);
    }
}
