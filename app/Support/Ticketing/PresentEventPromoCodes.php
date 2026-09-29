<?php

declare(strict_types=1);

namespace App\Support\Ticketing;

use App\Domain\Event\Models\Event;
use App\Domain\Ticketing\Actions\ResolvePromoCode;
use App\Domain\Ticketing\Models\PromoCode;
use App\Domain\Ticketing\Models\PromoCodeKind;

/**
 * Page « Codes promo » d'un événement : chaque code avec sa réduction, sa
 * période, ses utilisations et l'état qui en découle. Traverse Ticketing et
 * Event (fuseau de l'événement), d'où Support.
 */
final class PresentEventPromoCodes
{
    public function __construct(
        private readonly ResolvePromoCode $resolvePromoCode,
    ) {}

    /**
     * @return list<array{id: int, code: string, kind: string, percent: ?int, amountMinor: ?int, reduction: string, maxUses: ?int, uses: int, startsAt: ?string, endsAt: ?string, isActive: bool}>
     */
    public function handle(Event $event): array
    {
        return PromoCode::query()
            ->where('event_id', $event->id)
            ->orderBy('code')
            ->get()
            ->map(fn (PromoCode $promoCode): array => [
                'id' => $promoCode->id,
                'code' => $promoCode->code,
                'kind' => $promoCode->kind->value,
                'percent' => $promoCode->percent_bp === null ? null : intdiv($promoCode->percent_bp, 100),
                'amountMinor' => $promoCode->amount?->amountMinor(),
                'reduction' => $promoCode->kind === PromoCodeKind::Percent
                    ? intdiv((int) $promoCode->percent_bp, 100).' %'
                    : (string) $promoCode->amount?->format(),
                'maxUses' => $promoCode->max_uses,
                'uses' => $this->resolvePromoCode->uses($promoCode),
                // Saisies à l'heure de l'événement, réaffichées de même (règle 4.3).
                'startsAt' => $promoCode->starts_at?->setTimezone($event->timezone)->format('Y-m-d\TH:i'),
                'endsAt' => $promoCode->ends_at?->setTimezone($event->timezone)->format('Y-m-d\TH:i'),
                'isActive' => $promoCode->is_active,
            ])
            ->values()
            ->all();
    }
}
