<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\AttendeeConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Deux participants qui se sont rencontrés : l'un a scanné le badge de
 * l'autre (D8). La rencontre vaut dans les deux sens — chacun retrouve
 * l'autre —, mais on garde qui a scanné, pour savoir qui est allé vers qui.
 */
final class AttendeeConnection extends Model
{
    /** @use HasFactory<AttendeeConnectionFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'scanner_registration_id',
        'scanned_registration_id',
    ];

    protected static function newFactory(): AttendeeConnectionFactory
    {
        return AttendeeConnectionFactory::new();
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function scanner(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'scanner_registration_id');
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function scanned(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'scanned_registration_id');
    }
}
