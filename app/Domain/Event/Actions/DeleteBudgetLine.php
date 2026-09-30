<?php

declare(strict_types=1);

namespace App\Domain\Event\Actions;

use App\Domain\Event\Models\BudgetLine;
use App\Domain\Event\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Retire un poste du budget (D7). Suppression logique (règle 4.5) : la
 * ligne disparaît des totaux sans que son historique soit perdu.
 */
final class DeleteBudgetLine
{
    public function handle(BudgetLine $line, Event $event, User $editor): void
    {
        Gate::forUser($editor)->authorize('viewFinancials', $event->organization);

        $line->delete();
    }
}
