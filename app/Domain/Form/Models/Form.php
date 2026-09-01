<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

use App\Models\User;
use App\Support\Auditing\Auditable;
use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\FormFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pas de relation Eloquent event() ici : Domain/Form ne référence jamais
 * directement un modèle d'un autre module de Domain/ (règle de la section 3
 * du CLAUDE.md). event_id reste une simple colonne ; récupérer l'Event
 * complet, si besoin, se fait depuis l'appelant (contrôleur, action),
 * jamais via une relation portée par ce modèle.
 */
final class Form extends Model
{
    /** @use HasFactory<FormFactory> */
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'created_by',
        'name',
        'current_version_id',
    ];

    protected static function newFactory(): FormFactory
    {
        return FormFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<FormVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(FormVersion::class);
    }

    /**
     * @return BelongsTo<FormVersion, $this>
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class, 'current_version_id');
    }

    /**
     * La dernière version dans l'ordre chronologique, quel que soit son
     * statut — contrairement à currentVersion() qui ne pointe que vers la
     * version publiée. C'est elle qui détermine si le formulaire peut
     * encore être modifié en place (brouillon jamais publié) ou doit passer
     * par ReviseForm (une version publiée existe déjà).
     */
    public function latestVersion(): ?FormVersion
    {
        return $this->versions()->orderByDesc('version_number')->first();
    }

    /**
     * Un événement ne peut être publié que s'il a un formulaire actif
     * (PublishEvent, M1.2 du CDC) : c'est le cas dès qu'une version a
     * effectivement été publiée, peu importe qu'un brouillon plus récent
     * soit ensuite en préparation.
     */
    public function hasPublishedVersion(): bool
    {
        return $this->current_version_id !== null;
    }
}
