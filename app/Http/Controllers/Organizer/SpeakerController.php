<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Actions\DeleteSpeaker;
use App\Domain\Event\Actions\SaveSpeaker;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\Speaker;
use App\Domain\Organization\Actions\RecordAuditLog;
use App\Domain\Organization\Actions\StoreOrganizationImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Event\SaveSpeakerBriefingRequest;
use App\Http\Requests\Organizer\Event\SaveSpeakerRequest;
use App\Http\Requests\Organizer\Event\UploadSpeakerPhotoRequest;
use App\Mail\SpeakerPortalInvitationMail;
use App\Models\User;
use App\Support\Antivirus\FileScanStatus;
use App\Support\Events\PresentEventSessions;
use App\Support\Events\PresentEventSpeakers;
use App\Support\Events\PresentSpeakerPortal;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Intervenants d'un événement (D6) : leurs fiches, les sessions où ils
 * parlent, et leur portail — lien personnel, réponse au créneau et support
 * déposé.
 */
final class SpeakerController extends Controller
{
    public function index(int $event, PresentEventSpeakers $presentEventSpeakers, PresentEventSessions $presentEventSessions): Response
    {
        $eventModel = $this->event($event);
        Gate::authorize('update', $eventModel);

        return Inertia::render('Events/Speakers', [
            'event' => ['id' => $eventModel->id, 'title' => $eventModel->title, 'speakerBriefing' => $eventModel->speaker_briefing],
            'speakers' => $presentEventSpeakers->handle($eventModel, withPortal: true),
            'sessions' => $presentEventSessions->handle($eventModel),
            'subEventsUrl' => route('events.sub-events.index', $eventModel->id),
        ]);
    }

    public function store(SaveSpeakerRequest $request, int $event, SaveSpeaker $saveSpeaker): RedirectResponse
    {
        $eventModel = $this->event($event);
        /** @var User $user */
        $user = $request->user();

        $saveSpeaker->create($eventModel, $user, $request->validated(), $this->sessionIds($request));

        return back()->with('status', 'speaker-saved');
    }

    public function update(SaveSpeakerRequest $request, int $event, int $speaker, SaveSpeaker $saveSpeaker): RedirectResponse
    {
        $eventModel = $this->event($event);
        /** @var User $user */
        $user = $request->user();

        $saveSpeaker->update($this->speaker($eventModel, $speaker), $eventModel, $user, $request->validated(), $this->sessionIds($request));

        return back()->with('status', 'speaker-saved');
    }

    public function destroy(Request $request, int $event, int $speaker, DeleteSpeaker $deleteSpeaker): RedirectResponse
    {
        $eventModel = $this->event($event);
        /** @var User $user */
        $user = $request->user();

        $deleteSpeaker->handle($this->speaker($eventModel, $speaker), $eventModel, $user);

        return back()->with('status', 'speaker-removed');
    }

    /**
     * Photo de l'intervenant : elle rejoint la bibliothèque de
     * l'organisation, comme les autres images des pages invité.
     */
    public function uploadPhoto(UploadSpeakerPhotoRequest $request, int $event, int $speaker, StoreOrganizationImage $storeOrganizationImage): JsonResponse
    {
        $eventModel = $this->event($event);
        Gate::authorize('update', $eventModel);
        /** @var User $user */
        $user = $request->user();

        $image = $storeOrganizationImage->handle($eventModel->organization, $user, $request->file('photo'));
        $this->speaker($eventModel, $speaker)->update(['photo_path' => $image->path]);

        return response()->json(['photo_url' => Storage::disk('public')->url($image->path)]);
    }

    /**
     * Informations pratiques communes à tous les intervenants, affichées
     * sur leur portail.
     */
    public function saveBriefing(SaveSpeakerBriefingRequest $request, int $event): RedirectResponse
    {
        $eventModel = $this->event($event);
        Gate::authorize('update', $eventModel);

        $eventModel->update(['speaker_briefing' => $request->validated('speaker_briefing')]);

        return back()->with('status', 'speaker-briefing-saved');
    }

    /**
     * Envoi du lien personnel à l'intervenant.
     */
    public function sendPortalLink(Request $request, int $event, int $speaker, PresentSpeakerPortal $presentSpeakerPortal): RedirectResponse
    {
        $eventModel = $this->event($event);
        Gate::authorize('update', $eventModel);
        $speakerModel = $this->speaker($eventModel, $speaker);

        if ($speakerModel->email === null) {
            return back()->withErrors(['email' => "Ajoutez l'adresse e-mail de cet intervenant avant de lui envoyer son lien."]);
        }

        Mail::to($speakerModel->email)->queue($this->invitation($speakerModel, $eventModel, $presentSpeakerPortal));
        $speakerModel->update(['portal_sent_at' => CarbonImmutable::now()]);

        return back()->with('status', 'speaker-link-sent');
    }

    /**
     * Renouvellement du lien : l'ancien cesse aussitôt de fonctionner
     * (règle 4.6, un jeton reste révocable).
     */
    public function renewPortalLink(int $event, int $speaker): RedirectResponse
    {
        $eventModel = $this->event($event);
        Gate::authorize('update', $eventModel);

        $this->speaker($eventModel, $speaker)->update(['portal_token' => Str::random(40), 'portal_sent_at' => null]);

        return back()->with('status', 'speaker-link-renewed');
    }

    /**
     * Support déposé par l'intervenant : jamais servi avant le verdict de
     * l'antivirus, et son téléchargement est journalisé (§7 du CLAUDE.md).
     */
    public function downloadSupport(Request $request, int $event, int $speaker, RecordAuditLog $recordAuditLog): StreamedResponse
    {
        $eventModel = $this->event($event);
        Gate::authorize('update', $eventModel);
        $speakerModel = $this->speaker($eventModel, $speaker);

        abort_unless($speakerModel->support_scan_status === FileScanStatus::Clean && $speakerModel->support_path !== null, 404);

        $recordAuditLog->handle(
            action: 'speaker_support.downloaded',
            causer: $request->user(),
            subject: $speakerModel,
            metadata: ['event_id' => $eventModel->id, 'file_name' => $speakerModel->support_original_name],
        );

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($speakerModel->support_disk ?? (string) config('filesystems.registration_files_disk'));

        return $disk->download($speakerModel->support_path, $speakerModel->support_original_name ?? 'support', ['X-Content-Type-Options' => 'nosniff']);
    }

    private function invitation(Speaker $speaker, Event $event, PresentSpeakerPortal $presentSpeakerPortal): SpeakerPortalInvitationMail
    {
        return new SpeakerPortalInvitationMail(
            speakerName: $speaker->name,
            organizationName: $event->organization->name,
            eventTitle: $event->title,
            eventSchedule: $presentSpeakerPortal->schedule($event),
            portalUrl: route('speaker-portal.show', ['organization' => $event->organization->slug, 'token' => $speaker->portal_token]),
        );
    }

    /**
     * @return list<int>
     */
    private function sessionIds(SaveSpeakerRequest $request): array
    {
        return array_map(intval(...), $request->validated('session_ids') ?? []);
    }

    private function speaker(Event $event, int $id): Speaker
    {
        return Speaker::query()->where('event_id', $event->id)->findOrFail($id);
    }

    private function event(int $id): Event
    {
        return Event::query()->with('organization')->findOrFail($id);
    }
}
