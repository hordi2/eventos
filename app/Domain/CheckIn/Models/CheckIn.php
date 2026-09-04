<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Models;

use App\Models\User;
use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\CheckInFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * event_id/attendee_id/ticket_id restent de simples colonnes : Domain/
 * CheckIn ne référence jamais un modèle de Domain/Event, Domain/Form ou
 * Domain/Ticketing (section 3 du CLAUDE.md, test d'architecture).
 */
final class CheckIn extends Model
{
    /** @use HasFactory<CheckInFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'attendee_id',
        'ticket_id',
        'device_local_id',
        'direction',
        'status',
        'recorded_at',
        'checked_in_by',
    ];

    protected function casts(): array
    {
        return [
            'direction' => CheckInDirection::class,
            'status' => CheckInStatus::class,
            'recorded_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): CheckInFactory
    {
        return CheckInFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }
}
