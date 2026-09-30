<?php

declare(strict_types=1);

namespace App\Domain\Event\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Database\Factories\ProposalCallFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Appel à contributions d'un événement (D6) : la page publique où chacun
 * propose un sujet.
 *
 * @property ?CarbonImmutable $closes_at
 */
final class ProposalCall extends Model
{
    /** @use HasFactory<ProposalCallFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'is_open',
        'intro',
        'closes_at',
    ];

    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
            'closes_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): ProposalCallFactory
    {
        return ProposalCallFactory::new();
    }

    /**
     * Ouvert à de nouvelles propositions : l'organisateur l'a ouvert et la
     * date limite n'est pas passée.
     */
    public function acceptsProposals(): bool
    {
        return $this->is_open && ($this->closes_at === null || $this->closes_at->isFuture());
    }

    /**
     * @return HasMany<Proposal, $this>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }
}
