<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\InvalidOrderTransitionException;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Support\Payments\CardCheckoutProvider;

final class CreateStripeCheckout
{
    public function __construct(private readonly CardCheckoutProvider $provider) {}

    public function handle(Order $order, string $successUrl, string $cancelUrl): string
    {
        if ($order->status !== OrderStatus::Pending) {
            throw InvalidOrderTransitionException::notPending($order->id, $order->status);
        }

        return $this->provider->createCheckoutSession($order->id, $order->total, $successUrl, $cancelUrl);
    }
}
