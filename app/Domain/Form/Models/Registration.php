<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

use App\Support\Auditing\Auditable;
use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\RegistrationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pas de relation Eloquent event() : même règle que Form (section 3 du
 * CLAUDE.md) — event_id reste une simple colonne, jamais une relation vers
 * un modèle de Domain/Event.
 *
 * Pas de contact_id : ce champ appartient au modèle cible du CDC (§7.2),
 * mais Contact (M3, T-040) n'existe pas encore. L'identité de l'invité est
 * donc portée directement ici (email/first_name/last_name/phone_e164) —
 * décision prise pour ce ticket, à remplacer par une vraie relation Contact
 * quand T-040 sera construit.
 */
final class Registration extends Model
{
    /** @use HasFactory<RegistrationFactory> */
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'form_version_id',
        'status',
        'reservation_key',
        'email',
        'first_name',
        'last_name',
        'phone_e164',
        'source',
        'utm',
        'referrer',
        'ip_address',
        'user_agent',
        'locale',
        'registered_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'utm' => 'array',
            'registered_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): RegistrationFactory
    {
        return RegistrationFactory::new();
    }

    /**
     * @return BelongsTo<FormVersion, $this>
     */
    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }

    /**
     * @return HasMany<Attendee, $this>
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }

    /**
     * @return HasMany<RegistrationAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(RegistrationAnswer::class);
    }
}
