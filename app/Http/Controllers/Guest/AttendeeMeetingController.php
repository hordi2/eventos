<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\AttendeeMeeting;
use App\Domain\Form\Models\AttendeeMeetingStatus;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\ProposeAttendeeMeetingRequest;
use App\Support\Networking\PlanAttendeeMeeting;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Rendez-vous entre participants (D8), côté invité. Chaque geste part du
 * lien signé du participant : c'est lui qui dit qui propose et qui répond.
 */
final class AttendeeMeetingController extends Controller
{
    public function __construct(
        private readonly PlanAttendeeMeeting $planAttendeeMeeting,
    ) {}

    public function store(ProposeAttendeeMeetingRequest $request, string $organization, string $event, int $registration): RedirectResponse
    {
        $eventModel = $this->event($request);
        $me = $this->registration($eventModel, $registration);

        $meeting = $this->planAttendeeMeeting->propose(
            event: $eventModel,
            requester: $me,
            guestRegistrationId: (int) $request->validated('guest_registration_id'),
            // L'heure est saisie dans le fuseau de l'événement, stockée en UTC (règle 4.3).
            startsAt: CarbonImmutable::parse($request->validated('starts_at'), $eventModel->timezone)->utc(),
            durationMinutes: (int) ($request->validated('duration_minutes') ?? 30),
            place: $request->validated('place'),
            message: $request->validated('message'),
        );

        return back()->with('status', $meeting === null ? 'meeting-refused' : 'meeting-proposed');
    }

    public function answer(Request $request, string $organization, string $event, int $registration, int $meeting): RedirectResponse
    {
        $eventModel = $this->event($request);
        $me = $this->registration($eventModel, $registration);
        $status = AttendeeMeetingStatus::tryFrom((string) $request->string('status'));
        abort_if($status === null, 422);

        $model = AttendeeMeeting::query()->where('event_id', $eventModel->id)->findOrFail($meeting);
        $answered = $this->planAttendeeMeeting->answer($model, $me, $status);

        return back()->with('status', $answered ? 'meeting-answered' : 'meeting-refused');
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
