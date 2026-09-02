<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\RegistrationDraftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Progression d'une inscription en cours, avant soumission définitive
 * (SubmitRegistration). Pas de SoftDeletes : un brouillon est une donnée
 * éphémère de session, pas un enregistrement métier dont on garde la trace
 * une fois l'inscription réellement soumise (submitted_at) ou abandonnée.
 *
 * resume_token fait office de lien de reprise (M2.3 du CDC) : en l'absence
 * du module Messaging (M4), il n'est pas envoyé par e-mail — seulement
 * affiché à l'invité pour qu'il le conserve lui-même.
 */
final class RegistrationDraft extends Model
{
    /** @use HasFactory<RegistrationDraftFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'form_version_id',
        'resume_token',
        'identity',
        'answers',
        'registration_id',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'identity' => 'array',
            'answers' => 'array',
            'submitted_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): RegistrationDraftFactory
    {
        return RegistrationDraftFactory::new();
    }

    /**
     * @return BelongsTo<FormVersion, $this>
     */
    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
