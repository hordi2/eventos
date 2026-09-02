<?php

declare(strict_types=1);

namespace App\Support\Capacity\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\CapacityHoldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Une place effectivement retenue sur un « holder » (événement,
 * sous-événement, option de champ de formulaire...), identifié par
 * holder_type + holder_id plutôt que par une relation Eloquent — voir
 * ReserveCapacity pour le raisonnement complet.
 */
final class CapacityHold extends Model
{
    /** @use HasFactory<CapacityHoldFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'holder_type',
        'holder_id',
        'reservation_key',
        'quantity',
        'status',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'status' => CapacityHoldStatus::class,
            'released_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): CapacityHoldFactory
    {
        return CapacityHoldFactory::new();
    }
}
