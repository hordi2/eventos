<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Models\Event;
use App\Domain\Messaging\Models\MessageChannel;
use App\Domain\Organization\Actions\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\GuestList\SendGuestListInvitationsRequest;
use App\Jobs\SendGuestListInvitationsJob;
use App\Models\User;
use App\Support\GuestList\InvitationSendPlan;
use App\Support\GuestList\PlanInvitationSend;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/**
 * Envoi groupé des invitations depuis la liste d'invités : un aperçu (qui
 * recevra le message, qui en sera privé et pourquoi), puis l'envoi, en file
 * d'attente et journalisé comme tout envoi de masse (§7 du CLAUDE.md).
 */
final class EventInvitationSendController extends Controller
{
    public function preview(SendGuestListInvitationsRequest $request, int $event, PlanInvitationSend $planInvitationSend): JsonResponse
    {
        return response()->json($this->plan($request, $this->authorizedEvent($event), $planInvitationSend)->summary());
    }

    public function store(SendGuestListInvitationsRequest $request, int $event, PlanInvitationSend $planInvitationSend, RecordAuditLog $recordAuditLog): RedirectResponse
    {
        $eventModel = $this->authorizedEvent($event);
        $plan = $this->plan($request, $eventModel, $planInvitationSend);

        if ($plan->recipientIds === []) {
            return back()->withErrors(['send' => 'Aucun invité ne peut recevoir ce message : vérifiez leurs coordonnées ou changez de canal.']);
        }

        if (Cache::add("guest-list-send:{$eventModel->id}:{$request->validated('send_key')}", true, 3600)) {
            SendGuestListInvitationsJob::dispatch($eventModel->organization_id, $eventModel->id, (string) $request->validated('channel'), (int) $request->validated('template_id'), $plan->recipientIds);
            /** @var User $user */
            $user = $request->user();
            $recordAuditLog->handle('guest_list.invitations_sent', $user, $eventModel, [...$request->safe()->only(['channel', 'audience', 'mode', 'template_id']), ...$plan->summary()]);
        }

        return back()->with('status', 'invitations-sending');
    }

    private function plan(SendGuestListInvitationsRequest $request, Event $event, PlanInvitationSend $planInvitationSend): InvitationSendPlan
    {
        return $planInvitationSend->handle(
            $event,
            MessageChannel::from((string) $request->validated('channel')),
            (string) $request->validated('audience'),
            (string) $request->validated('mode'),
            $request->selectedIds(),
        );
    }

    private function authorizedEvent(int $event): Event
    {
        $eventModel = Event::query()->findOrFail($event);
        Gate::authorize('sendCommunications', $eventModel->organization);

        return $eventModel;
    }
}
