<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\AttendeeMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un message échangé entre deux participants d'un événement (D8).
 */
final class AttendeeMessage extends Model
{
    /** @use HasFactory<AttendeeMessageFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'from_registration_id',
        'to_registration_id',
        'body',
        'read_at',
        'removed_at',
        'removed_by',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'immutable_datetime',
            'removed_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): AttendeeMessageFactory
    {
        return AttendeeMessageFactory::new();
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'from_registration_id');
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'to_registration_id');
    }
}
