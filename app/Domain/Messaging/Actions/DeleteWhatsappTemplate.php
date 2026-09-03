<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Messaging\Models\WhatsappTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class DeleteWhatsappTemplate
{
    public function handle(WhatsappTemplate $template, User $deleter): void
    {
        Gate::forUser($deleter)->authorize('delete', $template);

        $template->delete();
    }
}
