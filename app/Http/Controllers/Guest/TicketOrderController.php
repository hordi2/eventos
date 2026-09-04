<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Domain\Event\Models\Event;
use App\Domain\Ticketing\Actions\CreateOrder;
use App\Domain\Ticketing\Actions\DetermineActivePriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Domain\Ticketing\TicketsUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\StoreTicketOrderRequest;
use App\Support\Capacity\Actions\GetRemainingCapacity;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Page publique de sélection des billets et panier (T-058, M5.4). Comme
 * RegistrationController : jamais d'authentification, resolve-guest-event
 * résout organisation + événement depuis le slug et pose le contexte
 * multi-tenant avant que la moindre requête ne s'exécute.
 */
final class TicketOrderController extends Controller
{
    /**
     * @var array<string, int>
     */
    private const ZERO_DECIMAL_CURRENCIES = ['XOF' => 0, 'XAF' => 0];

    public function show(Request $request): View
    {
        $event = $this->event($request);

        return view('guest.ticketing.show', [
            'event' => $event,
            'ticketTypes' => $this->ticketTypeOptions($event),
            // Un jeton par affichage du formulaire, jamais par tentative
            // d'envoi : voir StoreTicketOrderRequest pour le rôle exact.
            'checkoutToken' => (string) Str::uuid(),
        ]);
    }

    public function store(StoreTicketOrderRequest $request, string $organization, string $event, CreateOrder $action): RedirectResponse
    {
        $eventModel = $this->event($request);

        $items = $this->resolveItems($request->validated('items') ?? []);

        if ($items === []) {
            return back()->withErrors(['items' => 'Sélectionnez au moins un billet.'])->withInput();
        }

        $donation = $this->resolveDonation($request->validated('donation_amount'), $items);

        try {
            $order = $action->handle(
                $eventModel->organization_id,
                $eventModel->id,
                [
                    'name' => $request->validated('buyer_name'),
                    'email' => $request->validated('buyer_email'),
                    'phone' => $request->validated('buyer_phone'),
                ],
                $items,
                $request->validated('checkout_token'),
                donation: $donation,
                donationCause: $request->validated('donation_cause'),
            );
        } catch (TicketsUnavailableException|InvalidArgumentException $e) {
            return back()->withErrors(['items' => $e->getMessage()])->withInput();
        }

        // reservation_key (UUID), jamais l'ID séquentiel de la commande :
        // cette page expose le nom/e-mail de l'acheteur et ses billets,
        // elle ne doit pas être devinable en énumérant des entiers (même
        // principe que le qr_token d'un billet, §4.6 CLAUDE.md).
        return redirect()->route('guest.ticketing.payment.show', [$organization, $event, $order->reservation_key]);
    }

    /**
     * @param  array<string, mixed>  $rawItems
     * @return list<array{ticket_type_id: int, quantity: int}>
     */
    private function resolveItems(array $rawItems): array
    {
        $items = [];

        foreach ($rawItems as $ticketTypeId => $quantity) {
            if ((int) $quantity > 0) {
                $items[] = ['ticket_type_id' => (int) $ticketTypeId, 'quantity' => (int) $quantity];
            }
        }

        return $items;
    }

    /**
     * @param  list<array{ticket_type_id: int, quantity: int}>  $items
     */
    private function resolveDonation(?string $rawAmount, array $items): ?Money
    {
        if ($rawAmount === null || (float) $rawAmount <= 0) {
            return null;
        }

        $firstTicketType = TicketType::query()->find($items[0]['ticket_type_id'] ?? null);
        $currency = $firstTicketType->currency ?? 'EUR';
        $exponent = self::ZERO_DECIMAL_CURRENCIES[$currency] ?? 2;

        return Money::fromMinorUnits((int) round(((float) $rawAmount) * (10 ** $exponent)), $currency);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ticketTypeOptions(Event $event): array
    {
        $determineActivePriceTier = app(DetermineActivePriceTier::class);
        $getRemainingCapacity = app(GetRemainingCapacity::class);

        return TicketType::query()
            ->where('event_id', $event->id)
            ->where('is_active', true)
            ->orderBy('position')
            ->get()
            ->map(function (TicketType $ticketType) use ($determineActivePriceTier, $getRemainingCapacity): array {
                $typeRemaining = $getRemainingCapacity->handle('ticket_type', (string) $ticketType->id, $ticketType->total_quantity);

                if ($ticketType->is_free) {
                    return $this->option($ticketType, true, null, $typeRemaining, 'Gratuit', null);
                }

                if ($typeRemaining === 0) {
                    return $this->option($ticketType, false, null, 0, null, null);
                }

                $tier = $determineActivePriceTier->handle($ticketType);

                if ($tier === null) {
                    return $this->option($ticketType, false, null, 0, null, null);
                }

                $tierRemaining = $getRemainingCapacity->handle('price_tier', (string) $tier->id, $tier->quantity);
                $remaining = $this->effectiveRemaining($typeRemaining, $tierRemaining);

                return $this->option($ticketType, $remaining === null || $remaining > 0, $tier->amount, $remaining, $tier->amount->format(), $tier->name);
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function option(TicketType $ticketType, bool $available, ?Money $amount, ?int $remaining, ?string $priceLabel, ?string $tierName): array
    {
        return [
            'id' => $ticketType->id,
            'name' => $ticketType->name,
            'description' => $ticketType->description,
            'is_free' => $ticketType->is_free,
            'available' => $available,
            'price_label' => $priceLabel,
            'amount_minor' => $amount?->amountMinor() ?? 0,
            'currency' => $amount?->currency() ?? $ticketType->currency,
            'tier_name' => $tierName,
            'min_per_order' => $ticketType->min_per_order,
            'max_per_order' => $this->effectiveMax($ticketType, $remaining),
            'remaining' => $remaining,
        ];
    }

    private function effectiveRemaining(?int $a, ?int $b): ?int
    {
        if ($a === null) {
            return $b;
        }

        if ($b === null) {
            return $a;
        }

        return min($a, $b);
    }

    private function effectiveMax(TicketType $ticketType, ?int $remaining): ?int
    {
        if ($ticketType->max_per_order === null) {
            return $remaining;
        }

        if ($remaining === null) {
            return $ticketType->max_per_order;
        }

        return min($ticketType->max_per_order, $remaining);
    }

    private function event(Request $request): Event
    {
        return $request->attributes->get('guestEvent');
    }
}
