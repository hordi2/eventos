<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Models\Event;
use App\Domain\Messaging\Models\PushNotification;
use App\Domain\Messaging\Models\PushSubscription;
use App\Domain\Organization\Actions\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\PushNotification\ComposePushNotificationRequest;
use App\Jobs\SendPushNotificationsJob;
use App\Models\User;
use App\Support\WebPush\VapidKeyPair;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Composition et diffusion des notifications push de l'application
 * participant (D9) : sans charge utile (RFC 8292), leur contenu vit dans le
 * fil in-app de la page hub, jamais dans le push lui-même.
 */
final class PushNotificationController extends Controller
{
    public function index(int $event): Response
    {
        $eventModel = $this->authorizedEvent($event);

        return Inertia::render('Events/PushNotifications', [
            'event' => ['id' => $eventModel->id, 'title' => $eventModel->title],
            'notifications' => PushNotification::query()
                ->where('event_id', $eventModel->id)
                ->latest('created_at')
                ->get(['id', 'title', 'body', 'recipient_count', 'sent_at'])
                ->map(fn (PushNotification $notification): array => [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'body' => $notification->body,
                    'recipientCount' => $notification->recipient_count,
                    'sentAt' => $notification->sent_at?->toIso8601String(),
                ]),
            'subscriberCount' => PushSubscription::query()->where('event_id', $eventModel->id)->count(),
            'pushConfigured' => VapidKeyPair::isConfigured(),
        ]);
    }

    public function store(ComposePushNotificationRequest $request, int $event, RecordAuditLog $recordAuditLog): RedirectResponse
    {
        $eventModel = $this->authorizedEvent($event);

        if (! VapidKeyPair::isConfigured()) {
            return back()->withErrors(['push' => "L'envoi de notifications push n'est pas configuré sur cette installation."]);
        }

        $recipientCount = PushSubscription::query()->where('event_id', $eventModel->id)->count();

        if ($recipientCount === 0) {
            return back()->withErrors(['push' => "Aucun participant n'est abonné aux notifications pour le moment."]);
        }

        $notification = PushNotification::query()->create([
            'event_id' => $eventModel->id,
            'title' => $request->validated('title'),
            'body' => $request->validated('body'),
            'recipient_count' => $recipientCount,
            'sent_at' => CarbonImmutable::now(),
        ]);

        SendPushNotificationsJob::dispatch($eventModel->organization_id, $eventModel->id, $notification->id);

        /** @var User $user */
        $user = $request->user();
        $recordAuditLog->handle('push_notification.sent', $user, $eventModel, [
            'title' => $notification->title,
            'recipient_count' => $recipientCount,
        ]);

        return back()->with('status', 'push-notification-sent');
    }

    private function authorizedEvent(int $event): Event
    {
        $eventModel = Event::query()->findOrFail($event);
        Gate::authorize('sendCommunications', $eventModel->organization);
        abort_unless($eventModel->has_participant_app, 404);

        return $eventModel;
    }
}
