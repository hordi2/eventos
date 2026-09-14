<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Actions\MarkEventChecklistStep;
use App\Domain\Event\Models\Event;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Event\MarkEventChecklistStepRequest;
use App\Models\User;
use App\Support\Events\GetEventChecklist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page d'arrivée d'un événement juste après sa création : les étapes pour
 * le préparer, le lancer, l'organiser et le conclure.
 */
final class EventChecklistController extends Controller
{
    public function show(int $event, GetEventChecklist $getEventChecklist): Response
    {
        $event = Event::query()->findOrFail($event);

        Gate::authorize('viewGuests', $event->organization);

        return Inertia::render('Events/Checklist', [
            'checklist' => $getEventChecklist->handle($event),
            'canUpdate' => Gate::allows('update', $event),
        ]);
    }

    public function mark(MarkEventChecklistStepRequest $request, int $event, MarkEventChecklistStep $markEventChecklistStep): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $markEventChecklistStep->handle(Event::query()->findOrFail($event), $user, $request->step(), $request->boolean('completed'));

        return back();
    }
}
