<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Messaging\Models\EmailTemplate;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class CreateEmailTemplate
{
    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    public function handle(Organization $organization, User $creator, string $name, string $subject, array $blocks): EmailTemplate
    {
        Gate::forUser($creator)->authorize('create', [EmailTemplate::class, $organization]);

        return EmailTemplate::query()->create([
            'organization_id' => $organization->id,
            'created_by' => $creator->id,
            'name' => $name,
            'subject' => $subject,
            'blocks' => $blocks,
        ]);
    }
}
