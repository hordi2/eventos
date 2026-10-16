<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Models\Event;
use App\Domain\Ticketing\Actions\RefundOrder;
use App\Domain\Ticketing\InvalidOrderTransitionException;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Domain\Ticketing\Models\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Order\RefundOrderRequest;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Commandes d'un événement (M5.5) : ce qu'elles portent, et leur
 * remboursement total ou partiel.
 */
final class OrderController extends Controller
{
    public function index(int $event): Response
    {
        $eventModel = $this->findEvent($event);
        Gate::authorize('viewFinancials', $eventModel->organization);

        $orders = Order::query()
            ->where('event_id', $eventModel->id)
            ->with('payments')
            ->latest('created_at')
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'buyerName' => $order->buyer_name,
                'buyerEmail' => $order->buyer_email,
                'status' => $order->status->value,
                'total' => $order->total->format(),
                'refunded' => $order->refundedAmount()->format(),
                'hasRefund' => ! $order->refundedAmount()->isZero(),
                'remainingRefundable' => $order->remainingRefundable()->format(),
                'canRefund' => $order->status === OrderStatus::Paid && ! $order->remainingRefundable()->isZero(),
                'provider' => $order->payments->where('status', PaymentStatus::Succeeded)->last()?->provider,
                'paidAt' => $order->paid_at?->toIso8601String(),
            ]);

        return Inertia::render('Events/Orders', [
            'event' => ['id' => $eventModel->id, 'title' => $eventModel->title],
            'orders' => $orders,
        ]);
    }

    public function refund(RefundOrderRequest $request, int $event, int $order, RefundOrder $refundOrder): RedirectResponse
    {
        $eventModel = $this->findEvent($event);
        $orderModel = Order::query()->where('event_id', $eventModel->id)->findOrFail($order);

        $rawAmount = $request->validated('amount');
        $amount = $rawAmount === null || $rawAmount === ''
            ? null
            : Money::parse($rawAmount, $orderModel->total->currency());

        /** @var User $user */
        $user = $request->user();

        try {
            $refundOrder->handle($orderModel, $user, $amount);
        } catch (InvalidOrderTransitionException $exception) {
            return back()->withErrors(['amount' => $exception->getMessage()]);
        }

        return back()->with('status', 'order-refunded');
    }

    private function findEvent(int $event): Event
    {
        return Event::query()->findOrFail($event);
    }
}
