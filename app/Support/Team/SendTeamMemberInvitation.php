<?php

declare(strict_types=1);

namespace App\Support\Team;

use App\Domain\Organization\Models\MembershipInvitation;
use App\Domain\Organization\Models\Organization;
use App\Mail\TeamMemberInvitationMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

final class SendTeamMemberInvitation
{
    /**
     * Le jeton en clair n'existe qu'ici, le temps de construire le lien : la
     * base n'en garde que l'empreinte.
     */
    public function handle(MembershipInvitation $invitation, string $plainToken, User $inviter): void
    {
        $organization = Organization::query()->findOrFail($invitation->organization_id);

        Mail::to($invitation->email)->queue(new TeamMemberInvitationMail(
            inviterName: $inviter->name,
            organizationName: $organization->name,
            roleLabel: $invitation->role->label(),
            invitationUrl: route('team-invitations.show', ['organization' => $organization->slug, 'token' => $plainToken]),
        ));
    }
}
