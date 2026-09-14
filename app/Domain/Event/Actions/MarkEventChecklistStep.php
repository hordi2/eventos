<?php

declare(strict_types=1);

namespace App\Domain\Event\Actions;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventChecklistMark;
use App\Domain\Event\Models\EventChecklistStep;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class MarkEventChecklistStep
{
    public function handle(Event $event, User $actor, EventChecklistStep $step, bool $completed): EventChecklistMark
    {
        Gate::forUser($actor)->authorize('update', $event);

        if (! $step->canBeMarkedManually()) {
            throw new InvalidArgumentException("L'étape « {$step->title()} » se complète automatiquement.");
        }

        return EventChecklistMark::query()->updateOrCreate(
            ['event_id' => $event->id, 'step' => $step],
            [
                'organization_id' => $event->organization_id,
                'marked_by' => $completed ? $actor->id : null,
                'marked_at' => $completed ? CarbonImmutable::now() : null,
            ],
        );
    }
}
