<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\SeatingConstraintFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * event_id/guest_a_id/guest_b_id restent de simples colonnes (section 3 du
 * CLAUDE.md).
 */
final class SeatingConstraint extends Model
{
    /** @use HasFactory<SeatingConstraintFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'guest_a_type',
        'guest_a_id',
        'guest_b_type',
        'guest_b_id',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'type' => SeatingConstraintType::class,
        ];
    }

    protected static function newFactory(): SeatingConstraintFactory
    {
        return SeatingConstraintFactory::new();
    }
}
