<?php

declare(strict_types=1);

namespace App\Support\Registration;

use App\Domain\Form\Models\Registration;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;

/**
 * Encart « Votre don » de la page de confirmation d'inscription (T-056) :
 * montant, cause et, tant qu'il reste à régler, le lien vers le paiement —
 * ou, après un refus, vers la page qui propose de réessayer.
 */
final class PresentRegistrationDonation
{
    /**
     * @return array{amount: string, cause: ?string, status: string, paymentUrl: ?string}|null
     */
    public function handle(string $organizationSlug, string $eventSlug, Registration $registration): ?array
    {
        $order = Order::query()
            ->where('registration_id', $registration->id)
            ->with('donations')
            ->latest('id')
            ->first();

        if ($order === null) {
            return null;
        }

        return [
            'amount' => $order->total->format(),
            'cause' => $order->donations->first()?->cause,
            'status' => $order->status->value,
            'paymentUrl' => match ($order->status) {
                OrderStatus::Pending => route('guest.ticketing.payment.show', [$organizationSlug, $eventSlug, $order->reservation_key]),
                OrderStatus::Failed => route('guest.ticketing.payment.status', [$organizationSlug, $eventSlug, $order->reservation_key]),
                default => null,
            },
        ];
    }
}
