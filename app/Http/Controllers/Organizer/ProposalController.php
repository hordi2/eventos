<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Actions\DecideOnProposal;
use App\Domain\Event\Actions\SaveProposalCall;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\Proposal;
use App\Domain\Event\Models\ProposalCall;
use App\Domain\Event\Models\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Event\DecideOnProposalRequest;
use App\Http\Requests\Organizer\Event\SaveProposalCallRequest;
use App\Models\User;
use App\Support\Events\PresentProposals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Appel à contributions d'un événement (D6) : les réglages de l'appel, les
 * sujets reçus et leur évaluation.
 */
final class ProposalController extends Controller
{
    public function index(int $event, PresentProposals $presentProposals): Response
    {
        $eventModel = $this->event($event);
        Gate::authorize('update', $eventModel);
        $call = ProposalCall::query()->where('event_id', $eventModel->id)->first();

        return Inertia::render('Events/Proposals', [
            'event' => ['id' => $eventModel->id, 'title' => $eventModel->title],
            'call' => $presentProposals->call($call, $eventModel),
            'proposals' => $presentProposals->handle($eventModel),
            'publicUrl' => route('guest.proposals.show', [$eventModel->organization->slug, $eventModel->slug]),
            'speakersUrl' => route('events.speakers.index', $eventModel->id),
        ]);
    }

    public function save(SaveProposalCallRequest $request, int $event, SaveProposalCall $saveProposalCall): RedirectResponse
    {
        $eventModel = $this->event($event);
        Gate::authorize('update', $eventModel);

        $saveProposalCall->handle($eventModel, $request->validated());

        return back()->with('status', 'proposal-call-saved');
    }

    public function decide(DecideOnProposalRequest $request, int $event, int $proposal, DecideOnProposal $decideOnProposal): RedirectResponse
    {
        $eventModel = $this->event($event);
        /** @var User $user */
        $user = $request->user();

        $decideOnProposal->handle(
            Proposal::query()->where('event_id', $eventModel->id)->findOrFail($proposal),
            $eventModel,
            $user,
            ProposalStatus::from($request->string('status')->toString()),
            $request->filled('review_note') ? $request->string('review_note')->toString() : null,
            $request->filled('decision_message') ? $request->string('decision_message')->toString() : null,
        );

        return back()->with('status', 'proposal-decided');
    }

    private function event(int $id): Event
    {
        return Event::query()->with('organization')->findOrFail($id);
    }
}
