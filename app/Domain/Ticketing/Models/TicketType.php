<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Models;

use App\Models\User;
use App\Support\Auditing\Auditable;
use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\TicketTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * event_id reste une colonne simple, jamais une relation Eloquent :
 * Domain/Ticketing ne dépend d'aucun modèle de Domain/Event (section 3 du
 * CLAUDE.md, test d'architecture) — même règle que Registration ou
 * MessageAutomation vis-à-vis d'Event.
 */
final class TicketType extends Model
{
    /** @use HasFactory<TicketTypeFactory> */
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'created_by',
        'name',
        'description',
        'is_free',
        'currency',
        'min_per_order',
        'max_per_order',
        'total_quantity',
        'vat_mode',
        'vat_rate_bp',
        'fees_absorbed',
        'position',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
            'min_per_order' => 'integer',
            'max_per_order' => 'integer',
            'total_quantity' => 'integer',
            'vat_mode' => VatMode::class,
            'vat_rate_bp' => 'integer',
            'fees_absorbed' => 'boolean',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): TicketTypeFactory
    {
        return TicketTypeFactory::new();
    }

    /**
     * @return HasMany<PriceTier, $this>
     */
    public function priceTiers(): HasMany
    {
        return $this->hasMany(PriceTier::class)->orderBy('position');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
