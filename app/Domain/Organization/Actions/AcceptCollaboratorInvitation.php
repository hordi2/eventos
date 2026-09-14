<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Collaborator;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * L'appelant garantit que l'adresse du compte est celle de l'invitation et
 * que l'invitation n'a pas expiré (CollaboratorInvitationController).
 */
final class AcceptCollaboratorInvitation
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function handle(Collaborator $collaborator, User $user): void
    {
        DB::transaction(function () use ($collaborator, $user): void {
            $alreadyMember = Membership::query()
                ->where('organization_id', $collaborator->organization_id)
                ->where('user_id', $user->id)
                ->exists();

            // Un membre existant garde son rôle, plus large que celui de
            // collaborateur (une adhésion par organisation au plus).
            if (! $alreadyMember) {
                Membership::query()->create([
                    'organization_id' => $collaborator->organization_id,
                    'user_id' => $user->id,
                    'role' => MembershipRole::Collaborator,
                ]);
            }

            $collaborator->update([
                'user_id' => $user->id,
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
                action: 'collaborator.accepted',
                causer: $user,
                subject: $collaborator,
                organizationId: $collaborator->organization_id,
            );
        });
    }
}
