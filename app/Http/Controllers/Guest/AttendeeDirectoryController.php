<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\JoinAttendeeDirectoryRequest;
use App\Support\Networking\AttendeeConversations;
use App\Support\Networking\ExchangeBadgeContact;
use App\Support\Networking\GetAttendeeDirectory;
use App\Support\Networking\ModerateAttendeeMessages;
use App\Support\Networking\PlanAttendeeMeeting;
use App\Support\Networking\SuggestConnections;
use Carbon\CarbonImmutable;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Annuaire des participants (D8), côté invité.
 *
 * On n'y entre que par son propre lien signé, et seulement si l'organisateur
 * a ouvert l'annuaire. Un participant choisit d'y figurer, écrit la ligne
 * qui le présente, et peut en sortir à tout moment : son consentement est
 * alors effacé, pas seulement masqué.
 */
final class AttendeeDirectoryController extends Controller
{
    public function __construct(
        private readonly ExchangeBadgeContact $exchangeBadgeContact,
        private readonly SuggestConnections $suggestConnections,
        private readonly PlanAttendeeMeeting $planAttendeeMeeting,
        private readonly AttendeeConversations $attendeeConversations,
        private readonly ModerateAttendeeMessages $moderateAttendeeMessages,
    ) {}

    public function show(Request $request, string $organization, string $event, int $registration, GetAttendeeDirectory $getAttendeeDirectory): View
    {
        $eventModel = $this->event($request);
        $registrationModel = $this->registration($eventModel, $registration);

        abort_unless($eventModel->has_attendee_directory, 404);

        // Qui scanne ? Le lien signé que le participant vient d'ouvrir le
        // dit : on s'en souvient le temps de sa visite, pour que le scan
        // d'un badge sache de qui il vient.
        $request->session()->put('networking_registration_id', $registrationModel->id);

        return view('guest.registration.directory', [
            'event' => $eventModel,
            'registration' => $registrationModel,
            'attendees' => $getAttendeeDirectory->handle($eventModel),
            'joinUrl' => $request->fullUrl(),
            'badgeUrl' => $registrationModel->directory_consent_at === null
                ? null
                : route('guest.registration.badge', [$organization, $event, $this->exchangeBadgeContact->tokenFor($registrationModel)]),
            'badgeImageUrl' => $registrationModel->directory_consent_at === null
                ? null
                : route('guest.registration.badge-qr', [$organization, $event, $this->exchangeBadgeContact->tokenFor($registrationModel)]),
            'connections' => $this->exchangeBadgeContact->connectionsOf($registrationModel),
            'suggestions' => $this->suggestConnections->handle($eventModel, $registrationModel),
            'meetings' => $this->meetingsWithAnswerUrls($eventModel, $registrationModel, $organization, $event),
            'conversations' => $eventModel->has_attendee_messaging
                ? $this->attendeeConversations->forAttendee($eventModel, $registrationModel)
                : [],
            'interests' => implode(', ', $registrationModel->directory_interests ?? []),
            // Signées comme le lien qui mène ici : une action de networking
            // ne s'improvise pas depuis une adresse devinée.
            'actionUrls' => [
                'meeting' => $this->signed('guest.registration.meetings.store', [$organization, $event, $registrationModel->id]),
                'message' => $this->signed('guest.registration.messages.store', [$organization, $event, $registrationModel->id]),
                'block' => $this->signed('guest.registration.messages.block', [$organization, $event, $registrationModel->id]),
                'report' => $this->signed('guest.registration.messages.report', [$organization, $event, $registrationModel->id]),
            ],
            'blocked' => $this->moderateAttendeeMessages->blockedBy($registrationModel),
        ]);
    }

    /**
     * Le badge d'un participant, en image : c'est lui qu'on présente à
     * quelqu'un d'autre pour qu'il le scanne.
     */
    public function badgeQr(Request $request, string $organization, string $event, string $badgeToken): HttpResponse
    {
        $eventModel = $this->event($request);
        abort_unless($eventModel->has_attendee_directory, 404);

        $url = route('guest.registration.badge', [$organization, $event, $badgeToken]);

        return response((new Builder(writer: new PngWriter, data: $url, size: 420, margin: 16))->build()->getString(), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /**
     * Un badge scanné. L'appareil photo d'un téléphone n'ouvre qu'une
     * adresse : la rencontre s'enregistre donc sur une visite, ce qui ne
     * pose pas de problème puisque la même rencontre ne compte qu'une fois
     * (règle 4.4).
     */
    public function scan(Request $request, string $organization, string $event, string $badgeToken): View|RedirectResponse
    {
        $eventModel = $this->event($request);
        abort_unless($eventModel->has_attendee_directory, 404);

        $scannerId = $request->session()->get('networking_registration_id');
        $scanner = is_int($scannerId) ? Registration::query()->where('event_id', $eventModel->id)->find($scannerId) : null;

        if ($scanner === null || $scanner->status !== RegistrationStatus::Confirmed) {
            // Personne ne sait qui scanne : on le renvoie vers son propre
            // lien plutôt que de deviner.
            return view('guest.registration.badge-unknown', ['event' => $eventModel]);
        }

        $scanned = $this->exchangeBadgeContact->handle($eventModel, $scanner, $badgeToken);

        return redirect()
            ->to(URL::temporarySignedRoute('guest.registration.directory', now()->addDay(), [$organization, $event, $scanner->id]))
            ->with('status', $scanned === null ? 'badge-unknown' : 'badge-met')
            ->with('metName', $scanned === null ? null : trim("{$scanned->first_name} {$scanned->last_name}"));
    }

    public function update(JoinAttendeeDirectoryRequest $request, string $organization, string $event, int $registration): RedirectResponse
    {
        $eventModel = $this->event($request);
        $registrationModel = $this->registration($eventModel, $registration);

        abort_unless($eventModel->has_attendee_directory, 404);

        $joining = $request->boolean('join');

        $registrationModel->update([
            // Retrait : le consentement est effacé, et la ligne avec lui.
            'directory_consent_at' => $joining ? CarbonImmutable::now() : null,
            'directory_headline' => $joining ? ($request->string('headline')->toString() ?: null) : null,
            // Centres d'intérêt : ils nourrissent les suggestions, et
            // partent avec le consentement.
            'directory_interests' => $joining ? SuggestConnections::normalize($request->string('interests')->toString()) : null,
            // Partager son adresse est un second consentement : on peut
            // figurer à l'annuaire sans la donner.
            'shares_contact' => $joining && $request->boolean('shares_contact'),
            // Sortir de l'annuaire rend le badge inutilisable.
            'networking_token' => $joining ? $registrationModel->networking_token : null,
        ]);

        return back()->with('status', $joining ? 'directory-joined' : 'directory-left');
    }

    /**
     * Chaque rendez-vous porte son propre lien de réponse, signé : la
     * signature couvre l'identifiant, il ne peut donc pas être remplacé.
     *
     * @return list<array<string, mixed>>
     */
    private function meetingsWithAnswerUrls(Event $eventModel, Registration $registration, string $organization, string $event): array
    {
        return array_map(
            fn (array $meeting): array => [
                ...$meeting,
                'answerUrl' => $this->signed('guest.registration.meetings.answer', [$organization, $event, $registration->id, $meeting['id']]),
            ],
            $this->planAttendeeMeeting->forAttendee($eventModel, $registration),
        );
    }

    /**
     * @param  list<mixed>  $parameters
     */
    private function signed(string $name, array $parameters): string
    {
        return URL::temporarySignedRoute($name, CarbonImmutable::now()->addDay(), $parameters);
    }

    private function event(Request $request): Event
    {
        $event = $request->attributes->get('guestEvent');
        abort_unless($event instanceof Event, 404);

        return $event;
    }

    /**
     * L'inscription doit être celle de cet événement, et confirmée : un
     * refus ou une annulation n'a rien à faire dans l'annuaire.
     */
    private function registration(Event $event, int $registration): Registration
    {
        $model = Registration::query()
            ->where('event_id', $event->id)
            ->whereKey($registration)
            ->firstOrFail();

        abort_unless($model->status === RegistrationStatus::Confirmed, 404);

        return $model;
    }
}
