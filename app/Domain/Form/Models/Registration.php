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
 * Pas de relation Eloquent event() ni contact() : même règle que pour Form
 * vis-à-vis d'Event (section 3 du CLAUDE.md) — event_id/contact_id restent
 * de simples colonnes, jamais des relations vers un modèle d'un autre
 * module de Domain/. contact_id est renseigné par LinkRegistrationToContact
 * (app/Listeners, en dehors de Domain/Form et Domain/Contact — T-040) ;
 * l'identité reste dupliquée ici (email/first_name/last_name/phone_e164)
 * car une réponse déjà soumise ne doit jamais changer de sens si la fiche
 * contact est modifiée depuis (§4.7 du CLAUDE.md).
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
        'contact_id',
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
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'utm' => 'array',
            'registered_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
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

    /**
     * @return HasMany<RegistrationRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(RegistrationRevision::class);
    }
}
