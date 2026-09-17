<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Models\Event;
use App\Http\Controllers\Controller;
use App\Support\Registration\PresentEventDonations;
use App\Support\Registration\PresentMealPreferences;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rapports d'un événement tirés des réponses : les repas à commander et les
 * dons reçus ou promis.
 */
final class EventReportController extends Controller
{
    public function meals(int $event, PresentMealPreferences $presentMealPreferences): Response
    {
        $eventModel = $this->readableEvent($event);

        return Inertia::render('Events/Meals', [
            'event' => ['id' => $eventModel->id, 'title' => $eventModel->title],
            ...$presentMealPreferences->handle($eventModel),
        ]);
    }

    public function donations(int $event, PresentEventDonations $presentEventDonations): Response
    {
        $eventModel = $this->readableEvent($event);

        return Inertia::render('Events/Donations', [
            'event' => ['id' => $eventModel->id, 'title' => $eventModel->title],
            ...$presentEventDonations->handle($eventModel),
        ]);
    }

    private function readableEvent(int $event): Event
    {
        $eventModel = Event::query()->with('organization')->findOrFail($event);
        Gate::authorize('viewGuests', $eventModel->organization);

        return $eventModel;
    }
}
