<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\EventPoll;
use App\Domain\Form\Models\EventPollOption;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Support\LivePolls\ManageEventPolls;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Sondages en direct (D9), côté invité. On n'y entre que par son propre
 * lien signé, et seulement si l'organisateur a ouvert la fonctionnalité.
 */
final class EventPollController extends Controller
{
    public function __construct(
        private readonly ManageEventPolls $manageEventPolls,
    ) {}

    public function index(Request $request, string $organization, string $event, int $registration): View
    {
        $eventModel = $this->event($request);
        $registrationModel = $this->registration($eventModel, $registration);
        $expiry = $eventModel->edit_deadline ?? now()->addYear();

        $polls = array_map(
            fn (array $poll): array => [
                ...$poll,
                'options' => array_map(
                    fn (array $option): array => [
                        ...$option,
                        'voteUrl' => URL::temporarySignedRoute(
                            'guest.registration.polls.votes',
                            $expiry,
                            [$organization, $event, $registration, $poll['id'], $option['id']],
                        ),
                    ],
                    $poll['options'],
                ),
            ],
            $this->manageEventPolls->forAttendee($eventModel, $registrationModel),
        );

        return view('guest.registration.event-polls', [
            'event' => $eventModel,
            'polls' => $polls,
        ]);
    }

    public function vote(Request $request, string $organization, string $event, int $registration, int $poll, int $option): RedirectResponse
    {
        $eventModel = $this->event($request);
        $registrationModel = $this->registration($eventModel, $registration);
        $pollModel = EventPoll::query()->where('event_id', $eventModel->id)->findOrFail($poll);
        $optionModel = EventPollOption::query()->where('event_poll_id', $pollModel->id)->findOrFail($option);

        $this->manageEventPolls->vote($pollModel, $optionModel, $registrationModel);

        return back()->with('status', 'poll-voted');
    }

    private function event(Request $request): Event
    {
        $event = $request->attributes->get('guestEvent');
        abort_unless($event instanceof Event, 404);
        abort_unless($event->has_participant_app && $event->has_live_polls, 404);

        return $event;
    }

    private function registration(Event $event, int $registration): Registration
    {
        $model = Registration::query()->where('event_id', $event->id)->whereKey($registration)->firstOrFail();
        abort_unless($model->status === RegistrationStatus::Confirmed, 404);

        return $model;
    }
}
