<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipInvitation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * L'appelant garantit que l'adresse du compte est celle de l'invitation et
 * que l'invitation n'a pas expiré (TeamInvitationController).
 */
final class AcceptTeamMemberInvitation
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function handle(MembershipInvitation $invitation, User $user): Membership
    {
        return DB::transaction(function () use ($invitation, $user): Membership {
            $membership = Membership::query()
                ->where('organization_id', $invitation->organization_id)
                ->where('user_id', $user->id)
                ->first();

            // Déjà membre (par ex. collaborateur promu) : son rôle est
            // remplacé par celui de l'invitation plutôt qu'une seconde
            // adhésion, impossible de toute façon (une par organisation).
            if ($membership !== null) {
                $membership->update(['role' => $invitation->role]);
            } else {
                $membership = Membership::query()->create([
                    'organization_id' => $invitation->organization_id,
                    'user_id' => $user->id,
                    'role' => $invitation->role,
                ]);
            }

            $invitation->update([
                'accepted_at' => CarbonImmutable::now(),
                'invitation_token_hash' => null,
                'invitation_expires_at' => null,
            ]);

            // Le lien n'arrive que dans la boîte de cette adresse : l'avoir
            // ouvert prouve que l'e-mail appartient bien à ce compte.
            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            $this->recordAuditLog->handle(
                action: 'team_member.joined',
                causer: $user,
                subject: $invitation,
                metadata: ['role' => $invitation->role->value],
                organizationId: $invitation->organization_id,
            );

            return $membership;
        });
    }
}
