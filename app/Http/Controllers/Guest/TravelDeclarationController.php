<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Form\Models\TravelMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\DeclareTravelRequest;
use App\Support\Sustainability\CarpoolBoard;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Déclaration de déplacement et covoiturage (D12), côté invité.
 *
 * Le participant dit comment il vient : c'est la seule source honnête de
 * l'empreinte des déplacements. En échange, il voit qui part de chez lui et
 * peut proposer ou chercher une place.
 */
final class TravelDeclarationController extends Controller
{
    public function __construct(
        private readonly CarpoolBoard $carpoolBoard,
    ) {}

    public function show(Request $request, string $organization, string $event, int $registration): View
    {
        $eventModel = $this->event($request);
        $registrationModel = $this->registration($eventModel, $registration);

        return view('guest.registration.travel', [
            'event' => $eventModel,
            'registration' => $registrationModel,
            'modes' => TravelMode::options(),
            'board' => $this->carpoolBoard->handle($eventModel, $registrationModel),
            'formUrl' => $request->fullUrl(),
        ]);
    }

    public function update(DeclareTravelRequest $request, string $organization, string $event, int $registration): RedirectResponse
    {
        $eventModel = $this->event($request);
        $registrationModel = $this->registration($eventModel, $registration);

        $registrationModel->update([
            'travel_mode' => $request->validated('travel_mode'),
            'travel_distance_km' => $request->validated('travel_distance_km'),
            'travel_city' => $request->validated('travel_city'),
            'carpool_role' => $request->validated('carpool_role'),
            'travel_declared_at' => CarbonImmutable::now(),
        ]);

        return back()->with('status', 'travel-declared');
    }

    private function event(Request $request): Event
    {
        $event = $request->attributes->get('guestEvent');
        abort_unless($event instanceof Event, 404);
        abort_unless($event->has_carbon_report, 404);

        return $event;
    }

    private function registration(Event $event, int $registration): Registration
    {
        $model = Registration::query()->where('event_id', $event->id)->whereKey($registration)->firstOrFail();
        abort_unless($model->status === RegistrationStatus::Confirmed, 404);

        return $model;
    }
}
