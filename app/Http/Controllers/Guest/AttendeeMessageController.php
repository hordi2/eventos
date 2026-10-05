<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\SendAttendeeMessageRequest;
use App\Support\Networking\AttendeeConversations;
use App\Support\Networking\ModerateAttendeeMessages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Messagerie interne à l'événement (D8), côté invité.
 */
final class AttendeeMessageController extends Controller
{
    public function __construct(
        private readonly AttendeeConversations $attendeeConversations,
        private readonly ModerateAttendeeMessages $moderateAttendeeMessages,
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

    /**
     * Bloquer quelqu'un, ou le débloquer. Rien n'en est dit à l'intéressé :
     * ses messages ne partent simplement plus.
     */
    public function block(Request $request, string $organization, string $event, int $registration): RedirectResponse
    {
        $eventModel = $this->event($request);
        $me = $this->registration($eventModel, $registration);
        $otherId = (int) $request->integer('registration_id');

        if ($request->boolean('unblock')) {
            $this->moderateAttendeeMessages->unblock($me, $otherId);

            return back()->with('status', 'unblocked');
        }

        $blocked = $this->moderateAttendeeMessages->block($eventModel, $me, $otherId);

        return back()->with('status', $blocked ? 'blocked' : 'message-refused');
    }

    /**
     * Signaler un message reçu à l'organisateur.
     */
    public function report(Request $request, string $organization, string $event, int $registration): RedirectResponse
    {
        $eventModel = $this->event($request);
        $me = $this->registration($eventModel, $registration);

        $reported = $this->moderateAttendeeMessages->report(
            $eventModel,
            $me,
            (int) $request->integer('message_id'),
            $request->string('reason')->toString() ?: null,
        );

        return back()->with('status', $reported ? 'reported' : 'message-refused');
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
