<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Contact\Actions\AddEventInvitee;
use App\Domain\Contact\Actions\RemoveEventInvitee;
use App\Domain\Contact\Actions\UpdateEventInvitee;
use App\Domain\Contact\Models\EventInvitee;
use App\Domain\Contact\Support\GuestListTemplate;
use App\Domain\Event\Actions\UpdateEventGuestListAccess;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventAccessMode;
use App\Domain\Form\Support\FormSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\GuestList\SaveEventInviteeRequest;
use App\Http\Requests\Organizer\GuestList\UpdateGuestListAccessRequest;
use App\Models\User;
use App\Support\GuestList\PresentEventGuestList;
use App\Support\GuestList\RenewInvitationLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Liste des invités d'un événement (UC-03) : consultation, ajout et
 * modification à la main, modèle Excel à télécharger. L'import passe par
 * EventGuestImportController.
 */
final class EventGuestListController extends Controller
{
    public function index(Request $request, int $event, PresentEventGuestList $presentEventGuestList): Response
    {
        $eventModel = Event::query()->findOrFail($event);
        Gate::authorize('viewGuests', $eventModel->organization);
        $search = trim((string) $request->query('q', ''));

        return Inertia::render('GuestList/Index', [
            'event' => ['id' => $eventModel->id, 'title' => $eventModel->title],
            ...$presentEventGuestList->handle($eventModel, $search),
            'search' => $search,
            'maxCompanions' => FormSettings::MAX_COMPANIONS,
            ...$this->abilities($eventModel),
        ]);
    }

    public function store(SaveEventInviteeRequest $request, int $event, AddEventInvitee $addEventInvitee): RedirectResponse
    {
        $eventModel = Event::query()->findOrFail($event);
        /** @var User $user */
        $user = $request->user();

        $addEventInvitee->handle($eventModel->organization, $eventModel->id, $user, $request->toInviteeData());

        return back()->with('status', 'invitee-added');
    }

    public function update(SaveEventInviteeRequest $request, int $event, int $invitee, UpdateEventInvitee $updateEventInvitee): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updateEventInvitee->handle($this->findInvitee($event, $invitee), $user, $request->toInviteeData());

        return back()->with('status', 'invitee-updated');
    }

    public function destroy(Request $request, int $event, int $invitee, RemoveEventInvitee $removeEventInvitee): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $removeEventInvitee->handle($this->findInvitee($event, $invitee), $user);

        return back()->with('status', 'invitee-removed');
    }

    public function renewLink(Request $request, int $event, int $invitee, RenewInvitationLink $renewInvitationLink): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $renewInvitationLink->handle($this->findInvitee($event, $invitee), $user);

        return back()->with('status', 'invitation-link-renewed');
    }

    public function access(UpdateGuestListAccessRequest $request, int $event, UpdateEventGuestListAccess $updateEventGuestListAccess): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updateEventGuestListAccess->handle(Event::query()->findOrFail($event), $user, $request->boolean('closed'));

        return back()->with('status', $request->boolean('closed') ? 'guest-list-closed' : 'guest-list-opened');
    }

    public function template(int $event, GuestListTemplate $guestListTemplate): BinaryFileResponse
    {
        Gate::authorize('viewGuests', Event::query()->findOrFail($event)->organization);

        return response()->download($guestListTemplate->write(), 'modele-liste-invites-itaza.xlsx')->deleteFileAfterSend();
    }

    /**
     * @return array{canEdit: bool, needsAgreement: bool, accessClosed: bool, canChangeAccess: bool, canSend: bool}
     */
    private function abilities(Event $event): array
    {
        $canEdit = Gate::allows('updateGuests', $event->organization);

        return [
            'canEdit' => $canEdit,
            'needsAgreement' => $canEdit && $event->organization->sender_agreement_accepted_at === null,
            'accessClosed' => $event->access_mode === EventAccessMode::ClosedList,
            'canChangeAccess' => Gate::allows('update', $event),
            'canSend' => Gate::allows('sendCommunications', $event->organization),
        ];
    }

    private function findInvitee(int $event, int $invitee): EventInvitee
    {
        return EventInvitee::query()->where('event_id', $event)->findOrFail($invitee);
    }
}
