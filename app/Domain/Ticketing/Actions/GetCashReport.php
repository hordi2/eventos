<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Data\CashReportData;
use App\Domain\Ticketing\Data\CashReportEntryData;
use App\Domain\Ticketing\Models\Payment;
use App\Domain\Ticketing\Models\PaymentStatus;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;

/**
 * Rapport de caisse en fin d'événement (D3, T-054) : tous les encaissements
 * en espèces (paiement à l'arrivée) d'un événement, avec le total et le
 * détail par encaissement (montant, opérateur, horodatage).
 */
final class GetCashReport
{
    public function handle(Organization $organization, User $viewer, int $eventId): CashReportData
    {
        Gate::forUser($viewer)->authorize('viewFinancials', $organization);

        $payments = Payment::query()
            ->with('collector')
            ->whereHas('order', fn ($query) => $query->where('event_id', $eventId))
            ->where('provider', 'cash')
            ->where('status', PaymentStatus::Succeeded)
            ->orderBy('succeeded_at')
            ->get();

        $currency = $payments->first()?->amount->currency() ?? 'EUR';
        $total = $payments->reduce(
            fn (Money $carry, Payment $payment): Money => $carry->add($payment->amount),
            Money::zero($currency),
        );

        $entries = $payments->map(fn (Payment $payment): CashReportEntryData => new CashReportEntryData(
            paymentId: $payment->id,
            orderId: $payment->order_id,
            amount: $payment->amount,
            collectorName: $payment->collector?->name,
            collectedAt: $payment->succeeded_at,
        ))->all();

        return new CashReportData(total: $total, count: $payments->count(), entries: $entries);
    }
}
