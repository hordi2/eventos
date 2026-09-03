<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Models;

use App\Models\User;
use App\Support\Auditing\Auditable;
use App\Support\Casts\AsMoney;
use App\Support\Money;
use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property Money $amount
 */
final class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'order_id',
        'provider',
        'collected_by',
        'provider_payment_id',
        'status',
        'failure_reason',
        'amount',
        'attempted_at',
        'succeeded_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => AsMoney::class,
            'attempted_at' => 'immutable_datetime',
            'succeeded_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}
