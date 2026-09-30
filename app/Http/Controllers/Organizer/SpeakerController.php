<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Actions\DeleteSpeaker;
use App\Domain\Event\Actions\SaveSpeaker;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\Speaker;
use App\Domain\Organization\Actions\StoreOrganizationImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Event\SaveSpeakerRequest;
use App\Http\Requests\Organizer\Event\UploadSpeakerPhotoRequest;
use App\Models\User;
use App\Support\Events\PresentEventSessions;
use App\Support\Events\PresentEventSpeakers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Intervenants d'un événement (D6) : leurs fiches et les sessions où ils
 * parlent.
 */
final class SpeakerController extends Controller
{
    public function index(int $event, PresentEventSpeakers $presentEventSpeakers, PresentEventSessions $presentEventSessions): Response
    {
        $eventModel = $this->event($event);
        Gate::authorize('update', $eventModel);

        return Inertia::render('Events/Speakers', [
            'event' => ['id' => $eventModel->id, 'title' => $eventModel->title],
            'speakers' => $presentEventSpeakers->handle($eventModel),
            'sessions' => $presentEventSessions->handle($eventModel),
            'subEventsUrl' => route('events.sub-events.index', $eventModel->id),
        ]);
    }

    public function store(SaveSpeakerRequest $request, int $event, SaveSpeaker $saveSpeaker): RedirectResponse
    {
        $eventModel = $this->event($event);
        /** @var User $user */
        $user = $request->user();

        $saveSpeaker->create($eventModel, $user, $request->validated(), $this->sessionIds($request));

        return back()->with('status', 'speaker-saved');
    }

    public function update(SaveSpeakerRequest $request, int $event, int $speaker, SaveSpeaker $saveSpeaker): RedirectResponse
    {
        $eventModel = $this->event($event);
        /** @var User $user */
        $user = $request->user();

        $saveSpeaker->update($this->speaker($eventModel, $speaker), $eventModel, $user, $request->validated(), $this->sessionIds($request));

        return back()->with('status', 'speaker-saved');
    }

    public function destroy(Request $request, int $event, int $speaker, DeleteSpeaker $deleteSpeaker): RedirectResponse
    {
        $eventModel = $this->event($event);
        /** @var User $user */
        $user = $request->user();

        $deleteSpeaker->handle($this->speaker($eventModel, $speaker), $eventModel, $user);

        return back()->with('status', 'speaker-removed');
    }

    /**
     * Photo de l'intervenant : elle rejoint la bibliothèque de
     * l'organisation, comme les autres images des pages invité.
     */
    public function uploadPhoto(UploadSpeakerPhotoRequest $request, int $event, int $speaker, StoreOrganizationImage $storeOrganizationImage): JsonResponse
    {
        $eventModel = $this->event($event);
        Gate::authorize('update', $eventModel);
        /** @var User $user */
        $user = $request->user();

        $image = $storeOrganizationImage->handle($eventModel->organization, $user, $request->file('photo'));
        $this->speaker($eventModel, $speaker)->update(['photo_path' => $image->path]);

        return response()->json(['photo_url' => Storage::disk('public')->url($image->path)]);
    }

    /**
     * @return list<int>
     */
    private function sessionIds(SaveSpeakerRequest $request): array
    {
        return array_map(intval(...), $request->validated('session_ids') ?? []);
    }

    private function speaker(Event $event, int $id): Speaker
    {
        return Speaker::query()->where('event_id', $event->id)->findOrFail($id);
    }

    private function event(int $id): Event
    {
        return Event::query()->with('organization')->findOrFail($id);
    }
}
