<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Support\ParticipantApp\BuildParticipantAppManifest;
use App\Support\ParticipantApp\PresentParticipantAppHub;
use App\Support\WebPush\VapidKeyPair;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Application participant en marque blanche (D9) : une page web installable
 * sur l'écran d'accueil, qui réunit programme, plan du lieu, et un repère
 * vers l'annuaire (D8) quand l'organisateur l'a ouvert. On n'y entre que par
 * son propre lien signé, comme l'annuaire et le reste du D8.
 */
final class ParticipantAppController extends Controller
{
    public function show(Request $request, string $organization, string $event, int $registration, PresentParticipantAppHub $presentParticipantAppHub): View
    {
        $eventModel = $this->event($request);
        $registrationModel = $this->registration($eventModel, $registration);

        return view('guest.registration.participant-app', [
            'event' => $eventModel,
            'registration' => $registrationModel,
            'hub' => $presentParticipantAppHub->handle($eventModel),
            'manifestUrl' => route('guest.registration.application.manifest', [$organization, $event]),
            'directoryUrl' => $eventModel->has_attendee_directory
                ? URL::temporarySignedRoute('guest.registration.directory', $this->linkExpiry($eventModel), [$organization, $event, $registrationModel->id])
                : null,
            'pushSubscribeUrl' => URL::temporarySignedRoute('guest.registration.push-subscriptions.store', $this->linkExpiry($eventModel), [$organization, $event, $registrationModel->id]),
            'pushUnsubscribeUrl' => URL::temporarySignedRoute('guest.registration.push-subscriptions.destroy', $this->linkExpiry($eventModel), [$organization, $event, $registrationModel->id]),
            'vapidPublicKey' => VapidKeyPair::fromConfig()?->publicKey,
            'questionsUrl' => $eventModel->has_speaker_qa
                ? URL::temporarySignedRoute('guest.registration.questions', $this->linkExpiry($eventModel), [$organization, $event, $registrationModel->id])
                : null,
            'pollsUrl' => $eventModel->has_live_polls
                ? URL::temporarySignedRoute('guest.registration.polls', $this->linkExpiry($eventModel), [$organization, $event, $registrationModel->id])
                : null,
        ]);
    }

    /**
     * Le manifeste n'est pas signé : rien de ce qu'il porte n'est sensible,
     * et le navigateur doit pouvoir le relire après l'installation, bien
     * après l'expiration du lien reçu à l'inscription.
     */
    public function manifest(Request $request, string $organization, string $event, BuildParticipantAppManifest $buildParticipantAppManifest): JsonResponse
    {
        $eventModel = $this->event($request);
        abort_unless($eventModel->has_participant_app, 404);

        $startUrl = route('guest.registration.start', [$organization, $event]);

        return response()->json($buildParticipantAppManifest->handle($eventModel, $startUrl), 200, [
            'Content-Type' => 'application/manifest+json',
        ]);
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

    private function linkExpiry(Event $event): DateTimeInterface
    {
        return $event->edit_deadline ?? now()->addYear();
    }
}
