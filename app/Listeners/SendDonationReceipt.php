<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Support\PostalAddress;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Events\OrderPaid;
use App\Domain\Ticketing\Models\Donation;
use App\Domain\Ticketing\Models\Payment;
use App\Domain\Ticketing\Models\PaymentStatus;
use App\Mail\DonationReceiptMail;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

/**
 * Reçu de don envoyé par e-mail dès que le paiement est reçu (T-056,
 * décision produit) : carte, Mobile Money ou encaissement à l'accueil.
 * Traverse Ticketing, Event et Organization, d'où sa place hors de Domain,
 * comme SendConfirmationEmail. Volontairement synchrone pour la même
 * raison (modèle cloisonné porté par l'événement) : seul l'envoi part en
 * file, avec de simples valeurs.
 */
final class SendDonationReceipt
{
    /**
     * @var array<string, string>
     */
    private const METHODS = [
        'stripe' => 'Carte bancaire',
        'flutterwave' => 'Mobile Money',
        'cash' => "Espèces, à l'accueil",
    ];

    public function handle(OrderPaid $orderPaid): void
    {
        $order = $orderPaid->order;
        $donations = Donation::query()->where('order_id', $order->id)->orderBy('id')->get();

        // Sans adresse (commande anonymisée), pas de reçu à envoyer.
        if ($donations->isEmpty() || $order->buyer_email === '') {
            return;
        }

        $event = Event::query()->findOrFail($order->event_id);
        $organization = Organization::query()->findOrFail($order->organization_id);
        $paidAt = ($order->paid_at ?? CarbonImmutable::now())->setTimezone($event->timezone);

        $payment = Payment::query()
            ->where('order_id', $order->id)
            ->where('status', PaymentStatus::Succeeded)
            ->latest('succeeded_at')
            ->first();

        foreach ($donations as $donation) {
            Mail::to($order->buyer_email)->queue(new DonationReceiptMail([
                'number' => sprintf('DON-%s-%06d', $paidAt->format('Y'), $donation->id),
                'organization' => $organization->name,
                'event' => $event->title,
                'amount' => $donation->amount->format(),
                'paidAt' => $paidAt->translatedFormat('j F Y \à H\hi'),
                'method' => $payment !== null ? (self::METHODS[$payment->provider] ?? 'Paiement en ligne') : 'Paiement en ligne',
                'cause' => $donation->cause,
                'donorName' => $donation->donor_name ?? $order->buyer_name,
                'donorCompany' => $donation->donor_company,
                'donorAddress' => PostalAddress::format($donation->donor_address ?? []),
                'anonymous' => $donation->is_anonymous,
            ]));
        }
    }
}
