<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Models;

use App\Support\Auditing\Auditable;
use App\Support\Casts\AsMoney;
use App\Support\Money;
use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * event_id reste une colonne simple, jamais une relation Eloquent :
 * Domain/Ticketing ne dépend d'aucun modèle de Domain/Event (section 3 du
 * CLAUDE.md, test d'architecture) — même règle que TicketType.
 *
 * @property Money $total
 */
final class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'buyer_name',
        'buyer_email',
        'buyer_phone_e164',
        'status',
        'reservation_key',
        'total',
        'reserved_until',
        'paid_at',
        'failed_at',
        'expired_at',
        'refunded_at',
        'abandoned_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total' => AsMoney::class.':total_amount_minor,total_currency',
            'reserved_until' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
            'refunded_at' => 'immutable_datetime',
            'abandoned_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<Donation, $this>
     */
    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }
}
