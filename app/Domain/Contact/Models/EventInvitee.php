<?php

declare(strict_types=1);

namespace App\Domain\Contact\Models;

use App\Support\GuestList\InvitationToken;
use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\EventInviteeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un contact de l'organisation inscrit sur la liste d'invités d'un
 * événement (UC-03). event_id est une simple colonne : Domain/Contact ne
 * dépend pas de Domain/Event.
 *
 * companions_allowed nul signifie « illimité », dans la limite de la
 * plateforme (CompanionAllowance).
 */
final class EventInvitee extends Model
{
    /** @use HasFactory<EventInviteeFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'contact_id',
        'invitation_token',
        'contact_import_id',
        'group_key',
        'companions_allowed',
        'cc_email',
        'last_invited_at',
        'last_invited_via',
    ];

    protected function casts(): array
    {
        return [
            'companions_allowed' => 'integer',
            'last_invited_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): EventInviteeFactory
    {
        return EventInviteeFactory::new();
    }

    /**
     * Lien personnel, posé une fois pour toutes à la création : le nom de
     * l'invité suivi d'un tirage aléatoire (InvitationToken) — il se
     * reconnaît dès l'adresse, et le lien reste indevinable.
     */
    protected static function booted(): void
    {
        self::creating(function (self $invitee): void {
            // Contact chargé explicitement : la relation n'est pas encore
            // résolue à la création, et le chargement paresseux est interdit.
            $invitee->invitation_token ??= InvitationToken::for(Contact::query()->find($invitee->contact_id));
        });
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
