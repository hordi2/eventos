<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\MembershipInvitation;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\Team\SendTeamMemberInvitation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class ResendTeamMemberInvitation
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
        private readonly SendTeamMemberInvitation $sendTeamMemberInvitation,
    ) {}

    /**
     * Nouveau jeton à chaque envoi : le lien du précédent e-mail cesse de
     * fonctionner, seul le plus récent reste valable.
     */
    public function handle(MembershipInvitation $invitation, User $actor): void
    {
        Gate::forUser($actor)->authorize('inviteMembers', Organization::query()->findOrFail($invitation->organization_id));

        $plainToken = Str::random(48);

        $invitation->update([
            'invitation_token_hash' => MembershipInvitation::hashToken($plainToken),
            'invitation_expires_at' => CarbonImmutable::now()->addDays(MembershipInvitation::INVITATION_VALIDITY_DAYS),
        ]);

        $this->recordAuditLog->handle(
            action: 'team_member.invitation_resent',
            causer: $actor,
            subject: $invitation,
            organizationId: $invitation->organization_id,
        );

        $this->sendTeamMemberInvitation->handle($invitation, $plainToken, $actor);
    }
}
