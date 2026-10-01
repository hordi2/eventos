<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Models\Event;
use App\Domain\Page\Models\GuestBookMessage;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Livre d'or vu de l'organisateur (D1) : les mots laissés par ses invités,
 * qu'il peut masquer ou retirer. Rien n'attend sa validation — un livre
 * d'or qui se remplit en différé n'a pas le même goût —, mais il reste
 * maître de sa page.
 */
final class GuestBookController extends Controller
{
    public function index(int $event): Response
    {
        $eventModel = $this->event($event);

        return Inertia::render('Events/GuestBook', [
            'event' => ['id' => $eventModel->id, 'title' => $eventModel->title],
            'messages' => GuestBookMessage::query()
                ->where('event_id', $eventModel->id)
                ->latest('id')
                ->get()
                ->map(fn (GuestBookMessage $message): array => [
                    'id' => $message->id,
                    'author' => $message->author_name,
                    'message' => $message->message,
                    'isPublished' => $message->is_published,
                    'writtenAt' => $message->created_at?->setTimezone($eventModel->timezone)->translatedFormat('j F Y à H\hi') ?? '',
                ])
                ->values()
                ->all(),
        ]);
    }

    public function toggle(int $event, int $message): RedirectResponse
    {
        $messageModel = $this->message($this->event($event), $message);
        $messageModel->update(['is_published' => ! $messageModel->is_published]);

        return back()->with('status', 'guest-book-updated');
    }

    public function destroy(int $event, int $message): RedirectResponse
    {
        $this->message($this->event($event), $message)->delete();

        return back()->with('status', 'guest-book-removed');
    }

    private function message(Event $event, int $id): GuestBookMessage
    {
        return GuestBookMessage::query()->where('event_id', $event->id)->findOrFail($id);
    }

    private function event(int $id): Event
    {
        $event = Event::query()->with('organization')->findOrFail($id);
        Gate::authorize('update', $event);

        return $event;
    }
}
