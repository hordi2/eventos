<?php

declare(strict_types=1);

namespace App\Domain\Contact\Models;

use App\Support\Auditing\Auditable;
use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pas de relation Eloquent vers Registration : Domain/Contact ne dépend
 * jamais directement d'un modèle d'un autre module de Domain/ (section 3 du
 * CLAUDE.md, même règle que Domain/Form vis-à-vis de Domain/Event).
 * L'historique de participation (critère de T-040) est assemblé par le
 * contrôleur, qui peut librement traverser les deux modules.
 */
final class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'household_id',
        'first_name',
        'last_name',
        'email',
        'phone_e164',
        'company',
        'job_title',
        'preferred_language',
        'preferred_channel',
        'custom_fields',
        'email_consent',
        'email_consent_source',
        'email_consent_at',
        'sms_consent',
        'sms_consent_source',
        'sms_consent_at',
        'whatsapp_consent',
        'whatsapp_consent_source',
        'whatsapp_consent_at',
        'unsubscribed_at',
        'email_invalid_at',
        'email_invalid_reason',
        'whatsapp_invalid_at',
        'whatsapp_invalid_reason',
        'engagement_score',
    ];

    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
            'email_consent' => 'boolean',
            'email_consent_at' => 'immutable_datetime',
            'sms_consent' => 'boolean',
            'sms_consent_at' => 'immutable_datetime',
            'whatsapp_consent' => 'boolean',
            'whatsapp_consent_at' => 'immutable_datetime',
            'unsubscribed_at' => 'immutable_datetime',
            'email_invalid_at' => 'immutable_datetime',
            'whatsapp_invalid_at' => 'immutable_datetime',
            'engagement_score' => 'integer',
        ];
    }

    /**
     * Exclusion des envois (T-043) : choix de l'invité (unsubscribed_at) ou
     * constat automatique d'un bounce dur / d'une plainte (email_invalid_at)
     * — les deux excluent des envois futurs, mais restent des colonnes
     * distinctes pour ne jamais confondre un choix humain avec un fait
     * technique.
     */
    public function isEmailSuppressed(): bool
    {
        return $this->unsubscribed_at !== null || $this->email_invalid_at !== null;
    }

    /**
     * Contrairement à isEmailSuppressed(), exige explicitement le
     * consentement : WhatsApp Business (règle Meta) impose un opt-in
     * explicite avant tout message d'initiative commerciale, alors qu'un
     * e-mail transactionnel s'appuie sur le contexte de l'inscription elle-
     * même. Un bounce dur équivalent (whatsapp_invalid_at) exclut aussi.
     */
    public function isWhatsappSuppressed(): bool
    {
        return ! $this->whatsapp_consent || $this->whatsapp_invalid_at !== null;
    }

    protected static function newFactory(): ContactFactory
    {
        return ContactFactory::new();
    }

    /**
     * @return BelongsTo<Household, $this>
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'contact_tag')->withPivot('created_at');
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}") ?: ($this->email ?? "Contact #{$this->id}");
    }
}
