<?php

declare(strict_types=1);

namespace App\Support\Registration;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use App\Domain\Ticketing\Models\Donation;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Rapport « Dons et cadeaux » d'un événement : les dons promis dans un
 * formulaire d'inscription (T-056) comme ceux ajoutés à l'achat d'un
 * billet, avec leur donateur et l'état de leur paiement. Traverse Event,
 * Form (le donateur inscrit) et Ticketing, d'où Support.
 */
final class PresentEventDonations
{
    /**
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'paid' => 'Reçu',
        'payment_on_site' => "À régler à l'accueil",
        'pending' => 'En attente de paiement',
        'failed' => 'Paiement échoué',
        'expired' => 'Expiré',
        'refunded' => 'Remboursé',
    ];

    /**
     * @return array{
     *     donations: list<array{id: int, donor: string, email: string, isAnonymous: bool, amount: string, cause: ?string, status: string, statusLabel: string, promisedAt: string, paidAt: ?string, guestName: ?string}>,
     *     received: list<string>, promised: list<string>
     * }
     */
    public function handle(Event $event): array
    {
        $donations = Donation::query()
            ->whereIn('order_id', Order::query()->where('event_id', $event->id)->select('id'))
            ->with('order')
            ->latest('id')
            ->get();

        $guestNames = $this->guestNames($donations->pluck('order.registration_id')->filter()->all());
        $received = [];
        $promised = [];

        foreach ($donations as $donation) {
            $status = $donation->order?->status;

            if ($status === OrderStatus::Paid) {
                $received = $this->addTo($received, $donation->amount);
            } elseif (in_array($status, [OrderStatus::Pending, OrderStatus::PaymentOnSite], true)) {
                $promised = $this->addTo($promised, $donation->amount);
            }
        }

        return [
            'donations' => $donations
                ->map(fn (Donation $donation): array => $this->row($event, $donation, $guestNames))
                ->values()
                ->all(),
            'received' => array_map(fn (Money $total): string => $total->format(), array_values($received)),
            'promised' => array_map(fn (Money $total): string => $total->format(), array_values($promised)),
        ];
    }

    /**
     * @param  list<int>  $registrationIds
     * @return array<int, string>
     */
    private function guestNames(array $registrationIds): array
    {
        if ($registrationIds === []) {
            return [];
        }

        return Registration::query()
            ->whereKey($registrationIds)
            ->get()
            ->mapWithKeys(function (Registration $registration): array {
                $name = trim("{$registration->first_name} {$registration->last_name}");

                return [$registration->id => $name !== '' ? $name : $registration->email];
            })
            ->all();
    }

    /**
     * @param  array<string, Money>  $totals
     * @return array<string, Money>
     */
    private function addTo(array $totals, Money $amount): array
    {
        $totals[$amount->currency()] = isset($totals[$amount->currency()])
            ? $totals[$amount->currency()]->add($amount)
            : $amount;

        return $totals;
    }

    /**
     * @param  array<int, string>  $guestNames
     * @return array{id: int, donor: string, email: string, isAnonymous: bool, amount: string, cause: ?string, status: string, statusLabel: string, promisedAt: string, paidAt: ?string, guestName: ?string}
     */
    private function row(Event $event, Donation $donation, array $guestNames): array
    {
        $order = $donation->order;
        $status = $order?->status->value ?? 'pending';
        $localTime = fn (mixed $moment): string => CarbonImmutable::parse($moment)->setTimezone($event->timezone)->translatedFormat('j M Y \à H\hi');

        return [
            'id' => $donation->id,
            'donor' => $donation->donor_name ?? (string) $order?->buyer_name,
            'email' => (string) $order?->buyer_email,
            'isAnonymous' => $donation->is_anonymous,
            'amount' => $donation->amount->format(),
            'cause' => $donation->cause,
            'status' => $status,
            'statusLabel' => self::STATUS_LABELS[$status],
            'promisedAt' => $localTime($donation->created_at),
            'paidAt' => $order?->paid_at !== null ? $localTime($order->paid_at) : null,
            'guestName' => $order?->registration_id !== null ? ($guestNames[$order->registration_id] ?? null) : null,
        ];
    }
}
