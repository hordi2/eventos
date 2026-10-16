<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\InvalidOrderTransitionException;
use App\Domain\Ticketing\Models\Order;
use App\Support\Money;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Payments\CardCheckoutProvider;
use App\Support\Payments\InvalidWebhookSignatureException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Event as StripeEvent;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Traite un événement de webhook Stripe de façon idempotente (règle 4.4 du
 * CLAUDE.md), même mécanique que RecordWhatsappWebhookEvent (T-045bis) :
 * contrairement à Twilio, Stripe fournit son propre identifiant d'événement
 * stable (evt_...), utilisé tel quel comme event_id.
 *
 * L'insertion dans stripe_webhook_events et le traitement se font dans une
 * seule transaction : si le traitement lève une exception imprévue, les
 * deux sont annulés ensemble, et le rejeu automatique de Stripe retrouve un
 * événement non marqué traité plutôt qu'un événement perdu pour de bon
 * (bug corrigé : l'insertion seule n'engageait rien avant cette version).
 *
 * La vérification de signature (Webhook::constructEvent) est un calcul HMAC
 * local sur le corps brut de la requête — aucun appel réseau, donc pas
 * besoin de l'abstraire derrière une interface pour rester testable
 * (contrairement à CreateStripeCheckout).
 */
final class RecordStripeWebhookEvent
{
    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
        private readonly MarkOrderPaid $markOrderPaid,
        private readonly FailOrderPayment $failOrderPayment,
        private readonly CardCheckoutProvider $cardCheckoutProvider,
    ) {}

    public function handle(string $payload, string $signatureHeader): void
    {
        $event = $this->verifySignature($payload, $signatureHeader);

        DB::transaction(function () use ($event, $payload): void {
            $inserted = DB::table('stripe_webhook_events')->insertOrIgnore([[
                'provider' => 'stripe',
                'event_id' => $event->id,
                'payload' => $payload,
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]]);

            if ($inserted === 0) {
                Log::info("Webhook Stripe déjà traité, ignoré : {$event->id}.");

                return;
            }

            $this->apply($event);
        });
    }

    private function verifySignature(string $payload, string $signatureHeader): StripeEvent
    {
        $webhookSecret = config('services.stripe.webhook_secret');

        if ($webhookSecret === null) {
            throw InvalidWebhookSignatureException::forProvider('stripe');
        }

        try {
            return Webhook::constructEvent($payload, $signatureHeader, $webhookSecret);
        } catch (SignatureVerificationException) {
            throw InvalidWebhookSignatureException::forProvider('stripe');
        }
    }

    private function apply(StripeEvent $event): void
    {
        $object = $event->data->object;
        $orderId = (int) ($object->metadata->order_id ?? 0);
        $organizationId = (int) ($object->metadata->organization_id ?? 0);

        if ($orderId === 0 || $organizationId === 0) {
            Log::warning("Webhook Stripe reçu sans order_id/organization_id en métadonnée : {$event->id}.");

            return;
        }

        // organization_id vient de la métadonnée, jamais d'une relecture de
        // la commande : la row-level security PostgreSQL (règle 4.1 du
        // CLAUDE.md) bloquerait cette relecture tant que le contexte n'est
        // pas posé — le rôle applicatif n'a jamais BYPASSRLS
        // (docker/postgres/init.sql), withoutGlobalScopes() seul ne suffit
        // donc pas ici, contrairement à une organisation (table sans RLS).
        $this->currentOrganization->set($organizationId);

        $order = Order::query()->find($orderId);

        if ($order === null) {
            Log::warning("Webhook Stripe reçu pour une commande inconnue : #{$orderId}.");

            return;
        }

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($order, $object),
            'payment_intent.payment_failed' => $this->failOrderPayment->handle(
                $order,
                'stripe',
                (string) $object->id,
                (string) ($object->last_payment_error->message ?? 'Paiement refusé.'),
            ),
            default => Log::info("Événement Stripe ignoré : {$event->type}."),
        };
    }

    private function handleCheckoutCompleted(Order $order, object $object): void
    {
        $paymentIntentId = (string) ($object->payment_intent ?? $object->id);
        $amount = Money::fromMinorUnits((int) $object['amount_total'], mb_strtoupper((string) $object['currency']));

        try {
            $this->markOrderPaid->handle($order, 'stripe', $paymentIntentId, $amount);
        } catch (InvalidOrderTransitionException) {
            // La session Stripe (30 minutes minimum, voir
            // StripeCheckoutProvider) peut aboutir après que la réservation
            // de stock (15 minutes, CreateOrder) a expiré : l'argent est
            // bien encaissé par Stripe, la commande ne peut plus l'accepter
            // — on le rend aussitôt plutôt que de le garder sans billet.
            Log::warning("Commande #{$order->id} payée après l'expiration de sa réservation : remboursement automatique.", [
                'payment_intent' => $paymentIntentId,
            ]);

            $this->cardCheckoutProvider->refund($paymentIntentId, $amount);
        }
    }
}
