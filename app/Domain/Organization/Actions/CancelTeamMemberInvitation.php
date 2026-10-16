<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\MembershipInvitation;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class CancelTeamMemberInvitation
{
    public function __construct(private readonly RecordAuditLog $recordAuditLog) {}

    public function handle(MembershipInvitation $invitation, User $actor): void
    {
        Gate::forUser($actor)->authorize('inviteMembers', Organization::query()->findOrFail($invitation->organization_id));

        $invitation->delete();

        $this->recordAuditLog->handle(
            action: 'team_member.invitation_cancelled',
            causer: $actor,
            subject: $invitation,
            organizationId: $invitation->organization_id,
        );
    }
}
