<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\AttendeeBlockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un participant qui ne veut plus être joint par un autre (D8). Le blocage
 * vaut dans les deux sens pour l'écriture : on ne reçoit plus rien de lui,
 * et on ne lui écrit plus non plus.
 */
final class AttendeeBlock extends Model
{
    /** @use HasFactory<AttendeeBlockFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'blocker_registration_id',
        'blocked_registration_id',
    ];

    protected static function newFactory(): AttendeeBlockFactory
    {
        return AttendeeBlockFactory::new();
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function blocked(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'blocked_registration_id');
    }
}
