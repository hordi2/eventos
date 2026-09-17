<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Models\Event;
use App\Http\Controllers\Controller;
use App\Support\Registration\PresentEventAnswers;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Écran « Réponses aux questions » d'un événement : le tableau de bord
 * (EventDashboardController) compte les inscriptions, celui-ci montre ce que
 * les invités ont répondu.
 */
final class EventAnswerController extends Controller
{
    public function index(int $event, PresentEventAnswers $presentEventAnswers): Response
    {
        $eventModel = Event::query()->with('organization')->findOrFail($event);
        Gate::authorize('viewGuests', $eventModel->organization);

        return Inertia::render('Events/Answers', [
            'event' => ['id' => $eventModel->id, 'title' => $eventModel->title],
            ...$presentEventAnswers->handle($eventModel),
        ]);
    }
}
