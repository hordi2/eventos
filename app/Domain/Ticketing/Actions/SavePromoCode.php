<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Models\PromoCode;
use App\Domain\Ticketing\Models\PromoCodeKind;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * Crée ou modifie un code promo d'événement. Le mot est enregistré en
 * majuscules : l'acheteur le saisira comme il veut.
 */
final class SavePromoCode
{
    /**
     * @param  array<string, mixed>  $data  code, kind, percent, amount_minor, currency, max_uses, starts_at, ends_at, is_active
     */
    public function create(Organization $organization, int $eventId, User $creator, array $data): PromoCode
    {
        Gate::forUser($creator)->authorize('create', [PromoCode::class, $organization]);

        return PromoCode::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $eventId,
            'created_by' => $creator->id,
            ...$this->attributes($data),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PromoCode $promoCode, User $editor, array $data): PromoCode
    {
        Gate::forUser($editor)->authorize('update', $promoCode);

        $promoCode->update($this->attributes($data));

        return $promoCode->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $kind = $data['kind'] instanceof PromoCodeKind ? $data['kind'] : PromoCodeKind::from((string) $data['kind']);

        return [
            'code' => PromoCode::normalize((string) $data['code']),
            'kind' => $kind,
            // Pourcentage saisi en entier (10 = 10 %), stocké en points de base.
            'percent_bp' => $kind === PromoCodeKind::Percent ? (int) $data['percent'] * 100 : null,
            'amount' => $kind === PromoCodeKind::Amount ? Money::fromMinorUnits((int) $data['amount_minor'], (string) $data['currency']) : null,
            'max_uses' => isset($data['max_uses']) ? (int) $data['max_uses'] : null,
            'starts_at' => $this->moment($data['starts_at'] ?? null, (string) ($data['timezone'] ?? 'UTC')),
            'ends_at' => $this->moment($data['ends_at'] ?? null, (string) ($data['timezone'] ?? 'UTC')),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    /**
     * Dates saisies à l'heure de l'événement, stockées en UTC (règle 4.3).
     */
    private function moment(mixed $value, string $timezone): ?CarbonImmutable
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value, $timezone)->utc() : null;
    }
}
