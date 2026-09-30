<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Domain\Event\Actions\SubmitProposal;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventStatus;
use App\Domain\Event\Models\ProposalCall;
use App\Domain\Event\Models\ProposalFormat;
use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\SubmitProposalRequest;
use App\Support\Events\PresentSpeakerPortal;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Appel à contributions vu du public (D6) : ouvert à qui a le lien, sans
 * compte ni inscription préalable. L'URL porte le slug de l'organisation et
 * celui de l'événement pour poser le contexte multi-tenant (RLS) — mêmes
 * gardes que le portail intervenant, sans les barrières du parcours RSVP
 * (mot de passe, liste fermée), qui ne concernent pas un appel public.
 */
final class ProposalController extends Controller
{
    public function show(string $organization, string $event, PresentSpeakerPortal $presentSpeakerPortal): View
    {
        [$call, $eventModel] = $this->findCall($organization, $event);

        return view('guest.proposal.form', [
            'call' => $call,
            'event' => $eventModel,
            'schedule' => $presentSpeakerPortal->schedule($eventModel),
            'formats' => ProposalFormat::cases(),
            'submitUrl' => route('guest.proposals.store', [$organization, $event]),
        ]);
    }

    public function store(SubmitProposalRequest $request, string $organization, string $event, SubmitProposal $submitProposal): RedirectResponse
    {
        [$call] = $this->findCall($organization, $event);

        abort_unless($call->acceptsProposals(), 410, __("L'appel à contributions est clos."));

        $submitProposal->handle($call, $request->validated());

        return redirect()->route('guest.proposals.show', [$organization, $event])->with('status', 'proposal-submitted');
    }

    /**
     * @return array{ProposalCall, Event}
     */
    private function findCall(string $organizationSlug, string $eventSlug): array
    {
        $organizationModel = Organization::query()->where('slug', $organizationSlug)->firstOrFail();

        app(CurrentOrganization::class)->set($organizationModel);

        $eventModel = Event::query()->where('slug', $eventSlug)->firstOrFail();
        abort_if($eventModel->status === EventStatus::Archived, 410, __("Cet événement n'est plus d'actualité."));

        $call = ProposalCall::query()->where('event_id', $eventModel->id)->firstOrFail();
        // Un appel jamais ouvert n'a pas de page publique du tout.
        abort_unless($call->is_open, 404);

        return [$call, $eventModel];
    }
}
