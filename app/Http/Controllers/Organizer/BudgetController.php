<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Actions\DeleteBudgetLine;
use App\Domain\Event\Actions\SaveBudgetLine;
use App\Domain\Event\Models\BudgetCategory;
use App\Domain\Event\Models\BudgetLine;
use App\Domain\Event\Models\Event;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Event\SaveBudgetLineRequest;
use App\Models\User;
use App\Support\Budget\PresentEventBudget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Suivi budgétaire d'un événement (D7) : prévu contre réalisé, résultat et
 * seuil de rentabilité. Réservé à qui peut voir les chiffres
 * (viewFinancials), comme le rapport de caisse.
 */
final class BudgetController extends Controller
{
    public function index(int $event, PresentEventBudget $presentEventBudget): Response
    {
        $eventModel = $this->event($event);

        return Inertia::render('Events/Budget', [
            'event' => ['id' => $eventModel->id, 'title' => $eventModel->title, 'currency' => $eventModel->currency],
            'budget' => $presentEventBudget->handle($eventModel),
            'categories' => BudgetCategory::options(),
            'ticketsUrl' => route('events.ticket-types.index', $eventModel->id),
        ]);
    }

    public function store(SaveBudgetLineRequest $request, int $event, SaveBudgetLine $saveBudgetLine): RedirectResponse
    {
        $eventModel = $this->event($event);
        /** @var User $user */
        $user = $request->user();

        $saveBudgetLine->create($eventModel, $user, $request->validated());

        return back()->with('status', 'budget-line-saved');
    }

    public function update(SaveBudgetLineRequest $request, int $event, int $line, SaveBudgetLine $saveBudgetLine): RedirectResponse
    {
        $eventModel = $this->event($event);
        /** @var User $user */
        $user = $request->user();

        $saveBudgetLine->update($this->line($eventModel, $line), $eventModel, $user, $request->validated());

        return back()->with('status', 'budget-line-saved');
    }

    public function destroy(Request $request, int $event, int $line, DeleteBudgetLine $deleteBudgetLine): RedirectResponse
    {
        $eventModel = $this->event($event);
        /** @var User $user */
        $user = $request->user();

        $deleteBudgetLine->handle($this->line($eventModel, $line), $eventModel, $user);

        return back()->with('status', 'budget-line-removed');
    }

    private function line(Event $event, int $id): BudgetLine
    {
        return BudgetLine::query()->where('event_id', $event->id)->findOrFail($id);
    }

    private function event(int $id): Event
    {
        $event = Event::query()->with('organization')->findOrFail($id);
        Gate::authorize('viewFinancials', $event->organization);

        return $event;
    }
}
