<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Actions\CreateEvent;
use App\Domain\Event\Actions\UpdateEvent;
use App\Domain\Event\CannotDeleteEventException;
use App\Domain\Event\Models\Event;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Event\SaveSubEventRequest;
use App\Support\Events\DeleteSubEvent;
use App\Support\Events\PresentSubEvents;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Événements secondaires d'un événement (T-013) : sessions avec leurs
 * propres horaires, capacité et liste d'attente, que le bloc « Événements
 * secondaires » du formulaire propose ensuite aux invités.
 */
final class SubEventController extends Controller
{
    public function index(int $event, PresentSubEvents $presentSubEvents): Response
    {
        $parent = $this->findParent($event);
        Gate::authorize('update', $parent);

        return Inertia::render('Events/SubEvents', [
            'event' => ['id' => $parent->id, 'title' => $parent->title],
            'subEvents' => $presentSubEvents->handle($parent),
        ]);
    }

    public function store(SaveSubEventRequest $request, int $event, CreateEvent $createEvent): RedirectResponse
    {
        $parent = $this->findParent($event);
        $parent->loadMissing('organization');

        $createEvent->handle($parent->organization, $request->user(), [
            ...$request->validated(),
            'parent_event_id' => $parent->id,
            'timezone' => $parent->timezone,
            'type' => $parent->type,
            'audience' => $parent->audience,
        ]);

        return redirect()->route('events.sub-events.index', $parent->id);
    }

    public function update(SaveSubEventRequest $request, int $event, int $subEvent, UpdateEvent $updateEvent): RedirectResponse
    {
        $updateEvent->handle($this->findSubEvent($event, $subEvent), $request->user(), $request->validated());

        return redirect()->route('events.sub-events.index', $event);
    }

    public function destroy(Request $request, int $event, int $subEvent, DeleteSubEvent $deleteSubEvent): RedirectResponse
    {
        try {
            $deleteSubEvent->handle($this->findSubEvent($event, $subEvent), $request->user());
        } catch (CannotDeleteEventException $exception) {
            return redirect()->route('events.sub-events.index', $event)->withErrors(['subEvent' => $exception->getMessage()]);
        }

        return redirect()->route('events.sub-events.index', $event);
    }

    /**
     * Un seul niveau de hiérarchie : un événement secondaire n'a jamais ses
     * propres sessions.
     */
    private function findParent(int $id): Event
    {
        $event = Event::query()->findOrFail($id);
        abort_if($event->isSubEvent(), 404);

        return $event;
    }

    private function findSubEvent(int $parentId, int $id): Event
    {
        return Event::query()->where('parent_event_id', $parentId)->findOrFail($id);
    }
}
