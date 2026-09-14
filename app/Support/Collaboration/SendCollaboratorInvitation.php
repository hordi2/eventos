<?php

declare(strict_types=1);

namespace App\Support\Collaboration;

use App\Domain\Organization\Models\Collaborator;
use App\Domain\Organization\Models\Organization;
use App\Mail\CollaboratorInvitationMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

final class SendCollaboratorInvitation
{
    public function __construct(
        private readonly DescribeCollaboratorEvents $describeCollaboratorEvents,
    ) {}

    /**
     * Le jeton en clair n'existe qu'ici, le temps de construire le lien : la
     * base n'en garde que l'empreinte.
     */
    public function handle(Collaborator $collaborator, string $plainToken, User $inviter): void
    {
        $organization = Organization::query()->findOrFail($collaborator->organization_id);

        Mail::to($collaborator->email)->queue(new CollaboratorInvitationMail(
            inviterName: $inviter->name,
            organizationName: $organization->name,
            events: $this->describeCollaboratorEvents->handle($collaborator),
            invitationUrl: route('collaborator-invitations.show', ['organization' => $organization->slug, 'token' => $plainToken]),
        ));
    }
}
