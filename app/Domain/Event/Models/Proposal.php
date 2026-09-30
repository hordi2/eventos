<?php

declare(strict_types=1);

namespace App\Domain\Event\Models;

use App\Support\Auditing\Auditable;
use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\ProposalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Sujet proposé par quelqu'un qui n'a pas de compte Itaza (D6). Ses
 * coordonnées restent ici : elles ne rejoignent la base contacts que s'il
 * est retenu et devient intervenant.
 */
final class Proposal extends Model
{
    /** @use HasFactory<ProposalFactory> */
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'proposal_call_id',
        'proposer_name',
        'proposer_email',
        'proposer_role',
        'proposer_company',
        'proposer_bio',
        'title',
        'summary',
        'format',
        'duration_minutes',
        'status',
        'review_note',
        'decision_message',
        'decided_at',
        'decided_by',
        'speaker_id',
    ];

    protected function casts(): array
    {
        return [
            'format' => ProposalFormat::class,
            'status' => ProposalStatus::class,
            'duration_minutes' => 'integer',
            'decided_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): ProposalFactory
    {
        return ProposalFactory::new();
    }

    /**
     * @return BelongsTo<ProposalCall, $this>
     */
    public function call(): BelongsTo
    {
        return $this->belongsTo(ProposalCall::class, 'proposal_call_id');
    }

    /**
     * @return BelongsTo<Speaker, $this>
     */
    public function speaker(): BelongsTo
    {
        return $this->belongsTo(Speaker::class);
    }
}
