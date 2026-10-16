<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

use App\Models\User;
use App\Support\MultiTenancy\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Database\Factories\MembershipInvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Invitation à rejoindre l'organisation avec un rôle donné (Owner excepté).
 * Pas de trait Auditable : chaque action (invitation, acceptation, retrait)
 * écrit sa propre entrée d'audit, comme Collaborator.
 *
 * @property CarbonImmutable|null $invitation_expires_at
 * @property CarbonImmutable|null $accepted_at
 */
final class MembershipInvitation extends Model
{
    /** @use HasFactory<MembershipInvitationFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    public const INVITATION_VALIDITY_DAYS = 7;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'email',
        'role',
        'invited_by_user_id',
        'invitation_token_hash',
        'invitation_expires_at',
        'accepted_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['invitation_token_hash'];

    protected function casts(): array
    {
        return [
            'role' => MembershipRole::class,
            'invitation_expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): MembershipInvitationFactory
    {
        return MembershipInvitationFactory::new();
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isInvitationExpired(): bool
    {
        return ! $this->isAccepted()
            && $this->invitation_expires_at !== null
            && $this->invitation_expires_at->isPast();
    }

    public function status(): string
    {
        return match (true) {
            $this->isAccepted() => 'active',
            $this->isInvitationExpired() => 'expired',
            default => 'pending',
        };
    }
}
