<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Contact\Actions\ApplyTagToContacts;
use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\Tag;
use App\Domain\Event\Models\Event;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Segment\ApplyTagToSegmentRequest;
use App\Support\Segments\ComputeEventSegmentContacts;
use App\Support\Segments\EventSegment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class EventSegmentController extends Controller
{
    public function __construct(
        private readonly ComputeEventSegmentContacts $computeEventSegmentContacts,
    ) {}

    public function index(int $event): Response
    {
        $event = $this->findEvent($event);
        Gate::authorize('viewGuests', $event->organization);

        $segments = array_map(
            fn (EventSegment $segment): array => [
                'value' => $segment->value,
                'label' => $segment->label(),
                'count' => $this->computeEventSegmentContacts->query($event, $segment)->count(),
            ],
            EventSegment::cases(),
        );

        return Inertia::render('Segments/Index', [
            'event' => ['id' => $event->id, 'title' => $event->title],
            'segments' => $segments,
        ]);
    }

    public function show(int $event, string $segment): Response
    {
        $event = $this->findEvent($event);
        Gate::authorize('viewGuests', $event->organization);
        $eventSegment = $this->findSegment($segment);

        $paginated = $this->computeEventSegmentContacts->query($event, $eventSegment)
            ->with('household')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(50);

        $attendance = $this->tracksAttendance($eventSegment)
            ? $this->computeEventSegmentContacts->attendanceForContacts($event, $paginated->pluck('id')->all())
            : [];

        return Inertia::render('Segments/Show', [
            'event' => ['id' => $event->id, 'title' => $event->title],
            'segment' => ['value' => $eventSegment->value, 'label' => $eventSegment->label()],
            'contacts' => $paginated->through(fn (Contact $contact): array => [
                'id' => $contact->id,
                'full_name' => $contact->fullName(),
                'email' => $contact->email,
                'phone_e164' => $contact->phone_e164,
                'attendee' => $attendance[$contact->id] ?? null,
            ]),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name', 'color']),
            'canApplyTag' => Gate::allows('updateGuests', $event->organization),
            'canCheckIn' => Gate::allows('checkIn', $event->organization),
        ]);
    }

    private function tracksAttendance(EventSegment $segment): bool
    {
        return in_array($segment, [EventSegment::Confirmes, EventSegment::Presents, EventSegment::NoShow], true);
    }

    public function applyTag(ApplyTagToSegmentRequest $request, int $event, string $segment, ApplyTagToContacts $action): RedirectResponse
    {
        $event = $this->findEvent($event);
        $eventSegment = $this->findSegment($segment);
        $tag = Tag::query()->findOrFail($request->validated('tag_id'));

        $contactIds = $this->computeEventSegmentContacts->query($event, $eventSegment)->pluck('contacts.id')->all();

        $action->handle($event->organization, $request->user(), $tag, $contactIds);

        return redirect()->route('events.segments.show', [$event, $eventSegment->value]);
    }

    private function findEvent(int $id): Event
    {
        return Event::query()->findOrFail($id);
    }

    private function findSegment(string $value): EventSegment
    {
        return EventSegment::tryFrom($value) ?? throw new NotFoundHttpException("Segment inconnu : \"{$value}\".");
    }
}
