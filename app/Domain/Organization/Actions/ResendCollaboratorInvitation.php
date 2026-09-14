<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Collaborator;
use App\Models\User;
use App\Support\Collaboration\SendCollaboratorInvitation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class ResendCollaboratorInvitation
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
        private readonly SendCollaboratorInvitation $sendCollaboratorInvitation,
    ) {}

    /**
     * Nouveau jeton à chaque envoi : le lien du précédent e-mail cesse de
     * fonctionner, seul le plus récent reste valable.
     */
    public function handle(Collaborator $collaborator, User $actor): void
    {
        $plainToken = Str::random(48);

        $collaborator->update([
            'invitation_token_hash' => Collaborator::hashToken($plainToken),
            'invitation_expires_at' => CarbonImmutable::now()->addDays(Collaborator::INVITATION_VALIDITY_DAYS),
        ]);

        $this->recordAuditLog->handle(
            action: 'collaborator.invitation_resent',
            causer: $actor,
            subject: $collaborator,
            organizationId: $collaborator->organization_id,
        );

        $this->sendCollaboratorInvitation->handle($collaborator, $plainToken, $actor);
    }
}
