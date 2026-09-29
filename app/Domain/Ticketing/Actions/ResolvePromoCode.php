<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\InvalidPromoCodeException;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Domain\Ticketing\Models\PromoCode;
use App\Domain\Ticketing\Models\PromoCodeKind;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Retrouve le code promo saisi par un acheteur et refuse tout de suite ce
 * qui ne peut pas s'appliquer : mot inconnu, période close, nombre
 * d'utilisations atteint, devise différente de celle des billets.
 *
 * Les utilisations sont comptées sur les commandes elles-mêmes — payées, ou
 * en cours et encore réservées : pas de compteur à tenir à jour, donc jamais
 * de compteur faux après une commande abandonnée ou remboursée.
 */
final class ResolvePromoCode
{
    public function handle(int $eventId, string $code, Money $ticketsSubtotal): PromoCode
    {
        $promoCode = PromoCode::query()
            ->where('event_id', $eventId)
            ->where('code', PromoCode::normalize($code))
            ->first();

        if ($promoCode === null) {
            throw InvalidPromoCodeException::unknown();
        }

        if (! $promoCode->isOpenAt(CarbonImmutable::now())) {
            throw InvalidPromoCodeException::notOpen();
        }

        if ($promoCode->kind === PromoCodeKind::Amount && $promoCode->amount?->currency() !== $ticketsSubtotal->currency()) {
            throw InvalidPromoCodeException::otherCurrency();
        }

        if ($promoCode->max_uses !== null && $this->uses($promoCode) >= $promoCode->max_uses) {
            throw InvalidPromoCodeException::usedUp();
        }

        return $promoCode;
    }

    /**
     * Commandes qui tiennent ce code : celles qui sont payées, et celles en
     * cours dont la réservation court encore.
     */
    public function uses(PromoCode $promoCode): int
    {
        return Order::query()
            ->where('promo_code_id', $promoCode->id)
            ->where(fn ($query) => $query
                ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::PaymentOnSite->value])
                ->orWhere(fn ($pending) => $pending
                    ->where('status', OrderStatus::Pending->value)
                    ->where('reserved_until', '>', CarbonImmutable::now())))
            ->count();
    }
}
