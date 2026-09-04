<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Domain\Event\Models\Event;
use App\Domain\Ticketing\Actions\ChooseOnSitePayment;
use App\Domain\Ticketing\Actions\CreateStripeCheckout;
use App\Domain\Ticketing\Actions\GenerateTicketPdf;
use App\Domain\Ticketing\Actions\GenerateTicketQrToken;
use App\Domain\Ticketing\Actions\InitiateMobileMoneyPayment;
use App\Domain\Ticketing\Data\TicketPdfContext;
use App\Domain\Ticketing\InvalidOrderTransitionException;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Domain\Ticketing\Models\Ticket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\MobileMoneyPaymentRequest;
use App\Support\Payments\MobileMoneyProviderUnavailableException;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Choix et confirmation du moyen de paiement (T-059, M5.3). {order} porte
 * reservation_key (UUID), jamais l'ID séquentiel de la commande — voir
 * TicketOrderController::store().
 */
final class TicketOrderPaymentController extends Controller
{
    public function show(Request $request, string $organization, string $event, string $order): View|RedirectResponse
    {
        $orderModel = $this->order($request, $order);

        if ($orderModel->status !== OrderStatus::Pending) {
            return redirect()->route('guest.ticketing.payment.status', [$organization, $event, $order]);
        }

        return view('guest.ticketing.payment', ['event' => $this->event($request), 'order' => $orderModel]);
    }

    public function stripe(Request $request, string $organization, string $event, string $order, CreateStripeCheckout $action): RedirectResponse
    {
        $orderModel = $this->order($request, $order);

        $checkoutUrl = $action->handle(
            $orderModel,
            route('guest.ticketing.payment.status', [$organization, $event, $order]),
            route('guest.ticketing.payment.show', [$organization, $event, $order]),
        );

        return redirect()->away($checkoutUrl);
    }

    public function mobileMoney(MobileMoneyPaymentRequest $request, string $organization, string $event, string $order, InitiateMobileMoneyPayment $action): RedirectResponse
    {
        $orderModel = $this->order($request, $order);

        try {
            $action->handle(
                $orderModel,
                $request->validated('country_code'),
                $request->validated('phone_number'),
                $request->validated('network'),
            );
        } catch (MobileMoneyProviderUnavailableException) {
            // InitiateMobileMoneyPayment bascule déjà la commande sur le
            // paiement à l'arrivée en cas d'indisponibilité (mode dégradé,
            // annexe C du CDC) — rien à faire ici, juste continuer vers le
            // statut, qui affichera la confirmation correspondante.
        }

        return redirect()->route('guest.ticketing.payment.status', [$organization, $event, $order]);
    }

    public function onSite(Request $request, string $organization, string $event, string $order, ChooseOnSitePayment $action): RedirectResponse
    {
        $orderModel = $this->order($request, $order);

        try {
            $action->handle($orderModel);
        } catch (InvalidOrderTransitionException) {
            // Déjà résolue (payée, expirée...) entre l'affichage de la page
            // et ce clic — pas d'erreur, la page de statut montrera l'état réel.
        }

        return redirect()->route('guest.ticketing.payment.status', [$organization, $event, $order]);
    }

    /**
     * Écran d'attente Mobile Money avec relance de statut (AC de ce
     * ticket) : une balise meta-refresh recharge cette même page toutes
     * les quelques secondes plutôt que du JavaScript de polling — plus
     * léger, et fonctionne même si le JS est désactivé.
     */
    public function status(Request $request, string $organization, string $event, string $order): View|RedirectResponse
    {
        $orderModel = $this->order($request, $order);

        return match ($orderModel->status) {
            OrderStatus::Paid, OrderStatus::Refunded, OrderStatus::PaymentOnSite => redirect()->route('guest.ticketing.payment.confirmation', [$organization, $event, $order]),
            OrderStatus::Failed => redirect()->route('guest.ticketing.payment.show', [$organization, $event, $order])
                ->withErrors(['payment' => 'Le paiement a échoué. Vous pouvez réessayer.']),
            OrderStatus::Expired => view('guest.ticketing.expired', ['event' => $this->event($request), 'order' => $orderModel]),
            OrderStatus::Pending => view('guest.ticketing.waiting', ['event' => $this->event($request), 'order' => $orderModel]),
        };
    }

    public function confirmation(Request $request, string $organization, string $event, string $order): View
    {
        $orderModel = $this->order($request, $order)->load(['items.tickets', 'donations']);

        return view('guest.ticketing.confirmation', ['event' => $this->event($request), 'order' => $orderModel]);
    }

    public function downloadTicket(Request $request, string $organization, string $event, string $order, int $ticket, GenerateTicketQrToken $generateQr, GenerateTicketPdf $generatePdf): Response
    {
        $eventModel = $this->event($request);
        $orderModel = $this->order($request, $order);

        $ticketModel = Ticket::query()
            ->whereHas('orderItem', fn ($query) => $query->where('order_id', $orderModel->id))
            ->findOrFail($ticket);

        $qrToken = $generateQr->handle($ticketModel, CarbonImmutable::instance($eventModel->end_at ?? $eventModel->start_at)->addDay());

        $pdf = $generatePdf->handle($ticketModel, $qrToken, new TicketPdfContext(
            organizationName: $eventModel->organization->name,
            eventTitle: $eventModel->title,
            eventStartAt: $eventModel->start_at,
            eventTimezone: $eventModel->timezone,
            venueName: $eventModel->venue?->name,
            ticketTypeName: $ticketModel->ticketType->name,
            buyerName: $orderModel->buyer_name,
        ));

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"billet-{$ticketModel->id}.pdf\"",
        ]);
    }

    private function order(Request $request, string $reservationKey): Order
    {
        $event = $this->event($request);

        return Order::query()
            ->where('reservation_key', $reservationKey)
            ->where('event_id', $event->id)
            ->firstOrFail();
    }

    private function event(Request $request): Event
    {
        return $request->attributes->get('guestEvent');
    }
}
