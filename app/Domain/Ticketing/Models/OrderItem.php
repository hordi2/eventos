<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Models;

use App\Support\Casts\AsMoney;
use App\Support\Money;
use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Money $unit_amount
 */
final class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'order_id',
        'ticket_type_id',
        'price_tier_id',
        'name',
        'quantity',
        'unit_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_amount' => AsMoney::class.':unit_amount_minor,unit_currency',
        ];
    }

    protected static function newFactory(): OrderItemFactory
    {
        return OrderItemFactory::new();
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<TicketType, $this>
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    /**
     * @return BelongsTo<PriceTier, $this>
     */
    public function priceTier(): BelongsTo
    {
        return $this->belongsTo(PriceTier::class);
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
