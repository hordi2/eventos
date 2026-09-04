<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\SeatAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * event_id/guest_id restent de simples colonnes (section 3 du CLAUDE.md) :
 * l'invité (attendee_id ou ticket_id) appartient à Domain/Form ou
 * Domain/Ticketing, jamais référencé ici par une relation Eloquent.
 */
final class SeatAssignment extends Model
{
    /** @use HasFactory<SeatAssignmentFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'seating_table_id',
        'guest_type',
        'guest_id',
    ];

    /**
     * @return BelongsTo<SeatingTable, $this>
     */
    public function seatingTable(): BelongsTo
    {
        return $this->belongsTo(SeatingTable::class, 'seating_table_id');
    }

    protected static function newFactory(): SeatAssignmentFactory
    {
        return SeatAssignmentFactory::new();
    }
}
