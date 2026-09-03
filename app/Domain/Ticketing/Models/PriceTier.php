<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Models;

use App\Support\Casts\AsMoney;
use App\Support\Money;
use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\PriceTierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property Money $amount
 */
final class PriceTier extends Model
{
    /** @use HasFactory<PriceTierFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $table = 'ticket_price_tiers';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'ticket_type_id',
        'name',
        'amount',
        'quantity',
        'starts_at',
        'ends_at',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'amount' => AsMoney::class,
            'quantity' => 'integer',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'position' => 'integer',
        ];
    }

    protected static function newFactory(): PriceTierFactory
    {
        return PriceTierFactory::new();
    }

    /**
     * @return BelongsTo<TicketType, $this>
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }
}
