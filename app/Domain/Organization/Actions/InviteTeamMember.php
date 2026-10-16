<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\InvalidMembershipActionException;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipInvitation;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\Team\SendTeamMemberInvitation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class InviteTeamMember
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
        private readonly SendTeamMemberInvitation $sendTeamMemberInvitation,
    ) {}

    public function handle(Organization $organization, User $inviter, string $email, MembershipRole $role): MembershipInvitation
    {
        Gate::forUser($inviter)->authorize('inviteMembers', $organization);

        if ($role === MembershipRole::Owner || $role === MembershipRole::Collaborator) {
            throw InvalidMembershipActionException::cannotInviteAsOwner();
        }

        $this->assertNotAlreadyMember($organization, $email);

        $plainToken = Str::random(48);

        $invitation = DB::transaction(function () use ($organization, $inviter, $email, $role, $plainToken): MembershipInvitation {
            $invitation = MembershipInvitation::query()->updateOrCreate(
                ['organization_id' => $organization->id, 'email' => $email, 'accepted_at' => null],
                [
                    'role' => $role,
                    'invited_by_user_id' => $inviter->id,
                    'invitation_token_hash' => MembershipInvitation::hashToken($plainToken),
                    'invitation_expires_at' => CarbonImmutable::now()->addDays(MembershipInvitation::INVITATION_VALIDITY_DAYS),
                ],
            );

            $this->recordAuditLog->handle(
                action: 'team_member.invited',
                causer: $inviter,
                subject: $invitation,
                metadata: ['role' => $role->value],
                organizationId: $organization->id,
            );

            return $invitation;
        });

        // Hors transaction : aucun e-mail pour une invitation annulée par un rollback.
        $this->sendTeamMemberInvitation->handle($invitation, $plainToken, $inviter);

        return $invitation;
    }

    private function assertNotAlreadyMember(Organization $organization, string $email): void
    {
        $userId = User::query()->where('email', $email)->value('id');

        $isMember = $userId !== null && Membership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $userId)
            ->where('role', '!=', MembershipRole::Collaborator)
            ->exists();

        if ($isMember) {
            throw InvalidMembershipActionException::alreadyMember($email);
        }
    }
}
