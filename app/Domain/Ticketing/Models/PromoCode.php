<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Models;

use App\Models\User;
use App\Support\Auditing\Auditable;
use App\Support\Casts\AsMoney;
use App\Support\Money;
use App\Support\MultiTenancy\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Database\Factories\PromoCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Réduction sur les billets d'un événement (lot 2). Pas de relation
 * event() : Domain/Ticketing ne référence aucun modèle de Domain/Event
 * (section 3 du CLAUDE.md), event_id reste une simple colonne.
 *
 * @property ?Money $amount
 */
final class PromoCode extends Model
{
    /** @use HasFactory<PromoCodeFactory> */
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'created_by',
        'code',
        'kind',
        'percent_bp',
        'amount',
        'max_uses',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'kind' => PromoCodeKind::class,
            'percent_bp' => 'integer',
            'amount' => AsMoney::class.':amount_minor,amount_currency',
            'max_uses' => 'integer',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): PromoCodeFactory
    {
        return PromoCodeFactory::new();
    }

    /**
     * Le mot tel qu'il est enregistré : l'acheteur le saisit comme il veut.
     */
    public static function normalize(string $code): string
    {
        return mb_strtoupper(trim($code));
    }

    public function isOpenAt(CarbonImmutable $moment): bool
    {
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lessThanOrEqualTo($moment))
            && ($this->ends_at === null || $this->ends_at->greaterThan($moment));
    }

    /**
     * Réduction sur un sous-total de billets : jamais plus que le
     * sous-total lui-même, et toujours en entiers (§4.2 du CLAUDE.md).
     */
    public function discountFor(Money $subtotal): Money
    {
        $discountMinor = match ($this->kind) {
            PromoCodeKind::Percent => intdiv(2 * $subtotal->amountMinor() * (int) $this->percent_bp + 10_000, 2 * 10_000),
            PromoCodeKind::Amount => $this->amount?->amountMinor() ?? 0,
        };

        return Money::fromMinorUnits(min($discountMinor, $subtotal->amountMinor()), $subtotal->currency());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
