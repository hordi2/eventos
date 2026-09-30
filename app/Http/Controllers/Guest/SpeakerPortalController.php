<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Domain\Event\Actions\RespondToSpeakerSlot;
use App\Domain\Event\Actions\StoreSpeakerSupport;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventStatus;
use App\Domain\Event\Models\Speaker;
use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\RespondToSpeakerSlotRequest;
use App\Http\Requests\Guest\UploadSpeakerSupportRequest;
use App\Support\Events\PresentSpeakerPortal;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Portail intervenant (D6) : jamais d'authentification, jamais de compte
 * demandé. L'URL porte le slug de l'organisation pour poser le contexte
 * multi-tenant avant de chercher l'intervenant (RLS), puis le jeton
 * personnel, seule preuve d'accès — comme le lien d'un collaborateur.
 */
final class SpeakerPortalController extends Controller
{
    public function show(string $organization, string $token, PresentSpeakerPortal $presentSpeakerPortal): View
    {
        [$speaker, $event] = $this->findSpeaker($organization, $token);

        return view('guest.speaker.portal', [
            'portal' => $presentSpeakerPortal->handle($speaker, $event),
            'respondUrl' => route('speaker-portal.respond', ['organization' => $organization, 'token' => $token]),
            'supportUrl' => route('speaker-portal.support', ['organization' => $organization, 'token' => $token]),
        ]);
    }

    public function respond(RespondToSpeakerSlotRequest $request, string $organization, string $token, RespondToSpeakerSlot $respondToSpeakerSlot): RedirectResponse
    {
        [$speaker] = $this->findSpeaker($organization, $token);

        $respondToSpeakerSlot->handle(
            $speaker,
            $request->string('response')->toString() === 'accept',
            $request->filled('note') ? $request->string('note')->toString() : null,
        );

        return back()->with('status', 'speaker-response-saved');
    }

    public function uploadSupport(UploadSpeakerSupportRequest $request, string $organization, string $token, StoreSpeakerSupport $storeSpeakerSupport): RedirectResponse
    {
        [$speaker] = $this->findSpeaker($organization, $token);

        $storeSpeakerSupport->handle($speaker, $request->file('support'));

        return back()->with('status', 'speaker-support-saved');
    }

    /**
     * @return array{Speaker, Event}
     */
    private function findSpeaker(string $organizationSlug, string $token): array
    {
        $organization = Organization::query()->where('slug', $organizationSlug)->firstOrFail();

        app(CurrentOrganization::class)->set($organization);

        $speaker = Speaker::query()->where('portal_token', $token)->firstOrFail();
        $event = Event::query()->with('venue')->findOrFail($speaker->event_id);

        // Un événement archivé n'attend plus rien de personne.
        abort_if($event->status === EventStatus::Archived, 410, __("Cet événement n'est plus d'actualité."));

        return [$speaker, $event];
    }
}
