<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\AttendeeMeetingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un rendez-vous proposé par un participant à un autre, pendant
 * l'événement (D8).
 */
final class AttendeeMeeting extends Model
{
    /** @use HasFactory<AttendeeMeetingFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'requester_registration_id',
        'guest_registration_id',
        'starts_at',
        'duration_minutes',
        'place',
        'message',
        'status',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'answered_at' => 'immutable_datetime',
            'status' => AttendeeMeetingStatus::class,
            'duration_minutes' => 'integer',
        ];
    }

    protected static function newFactory(): AttendeeMeetingFactory
    {
        return AttendeeMeetingFactory::new();
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'requester_registration_id');
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'guest_registration_id');
    }
}
