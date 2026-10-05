<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\SendAttendeeMessageRequest;
use App\Support\Networking\AttendeeConversations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Messagerie interne à l'événement (D8), côté invité.
 */
final class AttendeeMessageController extends Controller
{
    public function __construct(
        private readonly AttendeeConversations $attendeeConversations,
    ) {}

    public function store(SendAttendeeMessageRequest $request, string $organization, string $event, int $registration): RedirectResponse
    {
        $eventModel = $this->event($request);
        $me = $this->registration($eventModel, $registration);

        $message = $this->attendeeConversations->send(
            $eventModel,
            $me,
            (int) $request->validated('recipient_registration_id'),
            (string) $request->validated('body'),
        );

        return back()->with('status', $message === null ? 'message-refused' : 'message-sent');
    }

    private function event(Request $request): Event
    {
        $event = $request->attributes->get('guestEvent');
        abort_unless($event instanceof Event, 404);

        return $event;
    }

    private function registration(Event $event, int $registration): Registration
    {
        $model = Registration::query()->where('event_id', $event->id)->whereKey($registration)->firstOrFail();
        abort_unless($model->status === RegistrationStatus::Confirmed, 404);

        return $model;
    }
}
