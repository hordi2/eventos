<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Messaging\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class UpdateEmailTemplate
{
    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    public function handle(EmailTemplate $template, User $updater, string $name, string $subject, array $blocks, ?bool $sharedWithClients = null): EmailTemplate
    {
        Gate::forUser($updater)->authorize('update', $template);

        $template->update([
            'name' => $name,
            'subject' => $subject,
            'blocks' => $blocks,
            // Absent : le partage ne change pas.
            ...($sharedWithClients === null ? [] : ['is_shared_with_clients' => $sharedWithClients]),
        ]);

        return $template;
    }
}
