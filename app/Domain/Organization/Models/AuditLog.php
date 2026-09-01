<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

use App\Models\User;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Immuable au niveau base de données (déclencheur PostgreSQL, voir la
 * migration) : ni BelongsToOrganization ni la RLS ne s'appliquent ici, pour
 * les mêmes raisons que Membership — organization_id est nullable (une
 * connexion peut être journalisée avant qu'un contexte d'organisation ne
 * soit résolu), et l'immutabilité est garantie par un mécanisme séparé, pas
 * par le cloisonnement multi-tenant.
 *
 * @property-read User|null $causer
 */
final class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'causer_type',
        'causer_id',
        'subject_type',
        'subject_id',
        'action',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function newFactory(): AuditLogFactory
    {
        return AuditLogFactory::new();
    }

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException('Les entrées du journal d\'audit sont immuables.');
        });

        self::deleting(function (): never {
            throw new LogicException('Les entrées du journal d\'audit sont immuables.');
        });
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
