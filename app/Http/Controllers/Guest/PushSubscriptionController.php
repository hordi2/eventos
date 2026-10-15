<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Messaging\Actions\SubscribeToPushNotifications;
use App\Domain\Messaging\Actions\UnsubscribeFromPushNotifications;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\SubscribeToPushNotificationsRequest;
use App\Http\Requests\Guest\UnsubscribeFromPushNotificationsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Abonnement du navigateur aux notifications push de l'application
 * participant (D9). Le contenu réel de la notification n'y transite jamais
 * (charge utile vide, RFC 8292) : il vit dans le fil in-app de la page hub.
 */
final class PushSubscriptionController extends Controller
{
    public function store(SubscribeToPushNotificationsRequest $request, string $organization, string $event, int $registration, SubscribeToPushNotifications $subscribeToPushNotifications): JsonResponse
    {
        $eventModel = $this->event($request);
        $registrationModel = $this->registration($eventModel, $registration);

        $subscribeToPushNotifications->handle($eventModel->id, $registrationModel->id, $request->validated('endpoint'));

        return response()->json(status: 204);
    }

    public function destroy(UnsubscribeFromPushNotificationsRequest $request, string $organization, string $event, int $registration, UnsubscribeFromPushNotifications $unsubscribeFromPushNotifications): JsonResponse
    {
        $eventModel = $this->event($request);
        $this->registration($eventModel, $registration);

        $unsubscribeFromPushNotifications->handle($request->validated('endpoint'));

        return response()->json(status: 204);
    }

    private function event(Request $request): Event
    {
        $event = $request->attributes->get('guestEvent');
        abort_unless($event instanceof Event, 404);
        abort_unless($event->has_participant_app, 404);

        return $event;
    }

    private function registration(Event $event, int $registration): Registration
    {
        $model = Registration::query()->where('event_id', $event->id)->whereKey($registration)->firstOrFail();
        abort_unless($model->status === RegistrationStatus::Confirmed, 404);

        return $model;
    }
}
