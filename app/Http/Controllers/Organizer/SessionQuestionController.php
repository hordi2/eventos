<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\SessionQuestion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\SessionQuestion\AnswerSessionQuestionRequest;
use App\Support\SpeakerQa\ManageSessionQuestions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Modération des questions au conférencier (D9), côté organisateur : on y
 * voit tout, y compris les questions masquées.
 */
final class SessionQuestionController extends Controller
{
    public function __construct(
        private readonly ManageSessionQuestions $manageSessionQuestions,
    ) {}

    public function index(int $event): Response
    {
        $eventModel = $this->authorizedEvent($event);

        return Inertia::render('Events/SessionQuestions', [
            'event' => ['id' => $eventModel->id, 'title' => $eventModel->title],
            'questions' => $this->manageSessionQuestions->forOrganizer($eventModel),
        ]);
    }

    public function answer(AnswerSessionQuestionRequest $request, int $event, int $question): RedirectResponse
    {
        $eventModel = $this->authorizedEvent($event);
        $questionModel = $this->question($eventModel, $question);

        $this->manageSessionQuestions->answer($questionModel, $request->validated('answer'));

        return back()->with('status', 'question-answered');
    }

    public function hide(int $event, int $question): RedirectResponse
    {
        $eventModel = $this->authorizedEvent($event);
        $questionModel = $this->question($eventModel, $question);

        $this->manageSessionQuestions->hide($questionModel);

        return back()->with('status', 'question-hidden');
    }

    public function restore(int $event, int $question): RedirectResponse
    {
        $eventModel = $this->authorizedEvent($event);
        $questionModel = $this->question($eventModel, $question);

        $this->manageSessionQuestions->restore($questionModel);

        return back()->with('status', 'question-restored');
    }

    private function question(Event $event, int $question): SessionQuestion
    {
        return SessionQuestion::query()->where('event_id', $event->id)->findOrFail($question);
    }

    private function authorizedEvent(int $event): Event
    {
        $eventModel = Event::query()->findOrFail($event);
        Gate::authorize('update', $eventModel);
        abort_unless($eventModel->has_speaker_qa, 404);

        return $eventModel;
    }
}
