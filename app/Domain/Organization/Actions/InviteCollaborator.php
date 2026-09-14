<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Collaborator;
use App\Domain\Organization\Models\CollaboratorPermission;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\Collaboration\SendCollaboratorInvitation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class InviteCollaborator
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
        private readonly SendCollaboratorInvitation $sendCollaboratorInvitation,
    ) {}

    /**
     * @param  array<int, CollaboratorPermission>  $permissions  permission par id d'événement
     */
    public function handle(Organization $organization, User $inviter, string $email, array $permissions): Collaborator
    {
        $plainToken = Str::random(48);

        $collaborator = DB::transaction(function () use ($organization, $inviter, $email, $permissions, $plainToken): Collaborator {
            $collaborator = Collaborator::query()->create([
                'organization_id' => $organization->id,
                'email' => $email,
                'invited_by_user_id' => $inviter->id,
                'invitation_token_hash' => Collaborator::hashToken($plainToken),
                'invitation_expires_at' => CarbonImmutable::now()->addDays(Collaborator::INVITATION_VALIDITY_DAYS),
            ]);

            $collaborator->syncEventPermissions($permissions);

            // Changement de permission journalisé (section 7 du CLAUDE.md) —
            // sans l'adresse e-mail, le journal d'audit étant immuable.
            $this->recordAuditLog->handle(
                action: 'collaborator.invited',
                causer: $inviter,
                subject: $collaborator,
                metadata: ['permissions' => array_map(fn (CollaboratorPermission $permission): string => $permission->value, $permissions)],
                organizationId: $organization->id,
            );

            return $collaborator;
        });

        // Hors transaction : aucun e-mail pour une invitation annulée par un rollback.
        $this->sendCollaboratorInvitation->handle($collaborator, $plainToken, $inviter);

        return $collaborator;
    }
}
