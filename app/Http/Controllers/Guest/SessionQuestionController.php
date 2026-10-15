<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Form\Models\SessionQuestion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\AskSessionQuestionRequest;
use App\Support\SpeakerQa\ManageSessionQuestions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Questions au conférencier (D9), côté invité. On n'y entre que par son
 * propre lien signé, et seulement si l'organisateur a ouvert la
 * fonctionnalité.
 */
final class SessionQuestionController extends Controller
{
    public function __construct(
        private readonly ManageSessionQuestions $manageSessionQuestions,
    ) {}

    public function index(Request $request, string $organization, string $event, int $registration): View
    {
        $eventModel = $this->event($request);
        $registrationModel = $this->registration($eventModel, $registration);
        $expiry = $eventModel->edit_deadline ?? now()->addYear();

        $questions = array_map(
            fn (array $question): array => [
                ...$question,
                'voteUrl' => URL::temporarySignedRoute(
                    'guest.registration.questions.votes',
                    $expiry,
                    [$organization, $event, $registration, $question['id']],
                ),
            ],
            $this->manageSessionQuestions->forAttendee($eventModel, $registrationModel),
        );

        return view('guest.registration.session-questions', [
            'event' => $eventModel,
            'registration' => $registrationModel,
            'questions' => $questions,
            'formUrl' => $request->fullUrl(),
        ]);
    }

    public function store(AskSessionQuestionRequest $request, string $organization, string $event, int $registration): RedirectResponse
    {
        $eventModel = $this->event($request);
        $registrationModel = $this->registration($eventModel, $registration);

        $this->manageSessionQuestions->ask($eventModel, $registrationModel, $request->validated('body'));

        return back()->with('status', 'question-asked');
    }

    public function upvote(Request $request, string $organization, string $event, int $registration, int $question): RedirectResponse
    {
        $eventModel = $this->event($request);
        $registrationModel = $this->registration($eventModel, $registration);
        $questionModel = SessionQuestion::query()->where('event_id', $eventModel->id)->findOrFail($question);

        $this->manageSessionQuestions->toggleUpvote($questionModel, $registrationModel);

        return back();
    }

    private function event(Request $request): Event
    {
        $event = $request->attributes->get('guestEvent');
        abort_unless($event instanceof Event, 404);
        abort_unless($event->has_participant_app && $event->has_speaker_qa, 404);

        return $event;
    }

    private function registration(Event $event, int $registration): Registration
    {
        $model = Registration::query()->where('event_id', $event->id)->whereKey($registration)->firstOrFail();
        abort_unless($model->status === RegistrationStatus::Confirmed, 404);

        return $model;
    }
}
