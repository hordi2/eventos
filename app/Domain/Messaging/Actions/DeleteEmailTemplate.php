<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Messaging\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class DeleteEmailTemplate
{
    public function handle(EmailTemplate $template, User $deleter): void
    {
        Gate::forUser($deleter)->authorize('delete', $template);

        $template->delete();
    }
}
