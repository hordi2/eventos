<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\EventPoll;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\EventPoll\CreateEventPollRequest;
use App\Support\LivePolls\ManageEventPolls;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sondages en direct (D9), côté organisateur.
 */
final class EventPollController extends Controller
{
    public function __construct(
        private readonly ManageEventPolls $manageEventPolls,
    ) {}

    public function index(int $event): Response
    {
        $eventModel = $this->authorizedEvent($event);

        return Inertia::render('Events/LivePolls', [
            'event' => ['id' => $eventModel->id, 'title' => $eventModel->title],
            'polls' => $this->manageEventPolls->forOrganizer($eventModel),
        ]);
    }

    public function store(CreateEventPollRequest $request, int $event): RedirectResponse
    {
        $eventModel = $this->authorizedEvent($event);

        $this->manageEventPolls->create($eventModel, $request->validated('question'), $request->validated('options'));

        return back()->with('status', 'poll-created');
    }

    public function open(int $event, int $poll): RedirectResponse
    {
        $eventModel = $this->authorizedEvent($event);
        $this->manageEventPolls->open($this->poll($eventModel, $poll));

        return back()->with('status', 'poll-opened');
    }

    public function close(int $event, int $poll): RedirectResponse
    {
        $eventModel = $this->authorizedEvent($event);
        $this->manageEventPolls->close($this->poll($eventModel, $poll));

        return back()->with('status', 'poll-closed');
    }

    private function poll(Event $event, int $poll): EventPoll
    {
        return EventPoll::query()->where('event_id', $event->id)->findOrFail($poll);
    }

    private function authorizedEvent(int $event): Event
    {
        $eventModel = Event::query()->findOrFail($event);
        Gate::authorize('update', $eventModel);
        abort_unless($eventModel->has_live_polls, 404);

        return $eventModel;
    }
}
