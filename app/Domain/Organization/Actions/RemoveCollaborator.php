<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Collaborator;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RemoveCollaborator
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function handle(Collaborator $collaborator, User $actor): void
    {
        DB::transaction(function () use ($collaborator, $actor): void {
            if ($collaborator->user_id !== null) {
                // Seule l'adhésion de collaborateur est retirée : quelqu'un
                // devenu membre à part entière entre-temps le reste.
                Membership::query()
                    ->where('organization_id', $collaborator->organization_id)
                    ->where('user_id', $collaborator->user_id)
                    ->where('role', MembershipRole::Collaborator)
                    ->get()
                    ->each(fn (Membership $membership): ?bool => $membership->delete());
            }

            $collaborator->delete();

            $this->recordAuditLog->handle(
                action: 'collaborator.removed',
                causer: $actor,
                subject: $collaborator,
                organizationId: $collaborator->organization_id,
            );
        });
    }
}
