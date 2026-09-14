<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Collaborator;
use App\Domain\Organization\Models\CollaboratorPermission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateCollaboratorPermissions
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * @param  array<int, CollaboratorPermission>  $permissions  permission par id d'événement
     */
    public function handle(Collaborator $collaborator, User $actor, array $permissions): void
    {
        DB::transaction(function () use ($collaborator, $actor, $permissions): void {
            $collaborator->syncEventPermissions($permissions);

            $this->recordAuditLog->handle(
                action: 'collaborator.permissions_updated',
                causer: $actor,
                subject: $collaborator,
                metadata: ['permissions' => array_map(fn (CollaboratorPermission $permission): string => $permission->value, $permissions)],
                organizationId: $collaborator->organization_id,
            );
        });
    }
}
