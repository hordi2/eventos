<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

use App\Models\User;
use App\Support\MultiTenancy\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Database\Factories\CollaboratorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Personne invitée sur un ou plusieurs événements d'une organisation, sans
 * rôle à l'échelle de l'organisation. Pas de trait Auditable : chaque action
 * (invitation, permissions, retrait) écrit sa propre entrée d'audit, et un
 * journal automatique consignerait l'empreinte du jeton d'invitation dans
 * une table immuable.
 *
 * @property CarbonImmutable|null $invitation_expires_at
 * @property CarbonImmutable|null $accepted_at
 */
final class Collaborator extends Model
{
    /** @use HasFactory<CollaboratorFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    public const INVITATION_VALIDITY_DAYS = 7;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'email',
        'user_id',
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
            'invitation_expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): CollaboratorFactory
    {
        return CollaboratorFactory::new();
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /**
     * @return HasMany<CollaboratorEventPermission, $this>
     */
    public function eventPermissions(): HasMany
    {
        return $this->hasMany(CollaboratorEventPermission::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    /**
     * Les événements absents de la liste repassent à « Aucun accès » plutôt
     * que d'être supprimés (voir la migration).
     *
     * @param  array<int, CollaboratorPermission>  $permissions  permission par id d'événement
     */
    public function syncEventPermissions(array $permissions): void
    {
        $this->eventPermissions()
            ->whereNotIn('event_id', array_keys($permissions))
            ->update(['permission' => CollaboratorPermission::None->value]);

        foreach ($permissions as $eventId => $permission) {
            CollaboratorEventPermission::query()->updateOrCreate(
                ['collaborator_id' => $this->id, 'event_id' => $eventId],
                ['organization_id' => $this->organization_id, 'permission' => $permission],
            );
        }
    }
}
