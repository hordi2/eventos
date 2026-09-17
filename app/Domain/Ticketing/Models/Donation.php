<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Models;

use App\Support\Auditing\Auditable;
use App\Support\Casts\AsMoney;
use App\Support\Money;
use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\DonationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Informations du donateur (T-056) : renseignées quand le don vient du
 * formulaire d'inscription, reprises sur le reçu envoyé par e-mail.
 *
 * @property Money $amount
 * @property array<string, string>|null $donor_address
 */
final class Donation extends Model
{
    /** @use HasFactory<DonationFactory> */
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'order_id',
        'amount',
        'cause',
        'donor_name',
        'donor_company',
        'donor_address',
        'is_anonymous',
    ];

    protected function casts(): array
    {
        return [
            'amount' => AsMoney::class,
            'donor_address' => 'array',
            'is_anonymous' => 'boolean',
        ];
    }

    protected static function newFactory(): DonationFactory
    {
        return DonationFactory::new();
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
