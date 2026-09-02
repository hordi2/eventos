<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\RegistrationRevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Photo complète (identité + réponses) d'une Registration juste avant une
 * modification par l'invité (T-033 : « historisation de la version
 * précédente ») — jamais modifiée après coup, pas de SoftDeletes (une
 * révision n'a pas de cycle de vie propre, elle suit celui de sa
 * Registration via la cascade de la migration).
 */
final class RegistrationRevision extends Model
{
    /** @use HasFactory<RegistrationRevisionFactory> */
    use BelongsToOrganization, HasFactory;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'registration_id',
        'snapshot',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): RegistrationRevisionFactory
    {
        return RegistrationRevisionFactory::new();
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
