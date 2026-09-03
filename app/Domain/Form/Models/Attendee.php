<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\AttendeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Le participant réel (§7.1 du CDC), distinct de la Registration qui porte
 * la soumission. Une Registration simple (T-030) crée toujours exactement
 * un Attendee, is_primary — l'inscription de groupe (T-032) en ajoutera
 * d'autres, chacun avec sa propre identité.
 */
final class Attendee extends Model
{
    /** @use HasFactory<AttendeeFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'registration_id',
        'first_name',
        'last_name',
        'email',
        'is_primary',
        'checked_in_at',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            // Marqué manuellement pour l'instant (T-042) — le vrai check-in
            // par scan QR viendra avec T-060/061.
            'checked_in_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): AttendeeFactory
    {
        return AttendeeFactory::new();
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
