<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\CheckIn\Data\GuestData;
use App\Domain\Event\Models\Event;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\CheckIn\KioskCodeRequest;
use App\Support\CheckIn\GetEventGuestList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kiosque d'accueil (lot 2) : une tablette laissée en libre-service le jour
 * J. L'invité s'y pointe seul, par QR code ou en cherchant son nom, et son
 * badge part à l'impression.
 *
 * La tablette reste connectée avec le compte de l'organisateur : le code à
 * quatre chiffres, choisi à l'ouverture, est ce qui empêche un invité de
 * quitter le kiosque pour le reste du back-office.
 */
final class KioskController extends Controller
{
    private const MIN_SEARCH = 3;

    private const MAX_RESULTS = 8;

    public function start(KioskCodeRequest $request, int $event): RedirectResponse
    {
        $eventModel = $this->event($event);
        Gate::authorize('checkIn', $eventModel->organization);

        $request->session()->put($this->sessionKey($eventModel), Hash::make($request->string('code')->toString()));

        return redirect()->route('events.kiosk.show', $eventModel->id);
    }

    public function show(Request $request, int $event): Response|RedirectResponse
    {
        $eventModel = $this->event($event);
        Gate::authorize('checkIn', $eventModel->organization);

        if (! $request->session()->has($this->sessionKey($eventModel))) {
            return redirect()->route('events.check-in.index', $eventModel->id);
        }

        return Inertia::render('CheckIn/Kiosk', [
            'event' => ['id' => $eventModel->id, 'title' => $eventModel->title],
            'organizationName' => $eventModel->organization->name,
            'checkInUrl' => route('events.check-in.index', $eventModel->id),
        ]);
    }

    /**
     * Recherche par nom : jamais la liste entière, qui resterait lisible
     * dans la page d'une tablette laissée sans surveillance.
     */
    public function search(Request $request, int $event, GetEventGuestList $getEventGuestList): JsonResponse
    {
        $eventModel = $this->event($event);
        Gate::authorize('checkIn', $eventModel->organization);
        abort_unless($request->session()->has($this->sessionKey($eventModel)), 403);

        $term = trim($request->string('q')->toString());

        if (mb_strlen($term) < self::MIN_SEARCH) {
            return response()->json(['guests' => []]);
        }

        $guests = array_slice($getEventGuestList->handle($eventModel, $term), 0, self::MAX_RESULTS);

        return response()->json([
            'guests' => array_map(fn (GuestData $guest): array => [
                'guest_type' => $guest->guestType,
                'id' => $guest->id,
                'name' => $guest->name,
                'checked_in' => $guest->checkedIn,
            ], $guests),
        ]);
    }

    public function exit(KioskCodeRequest $request, int $event): RedirectResponse
    {
        $eventModel = $this->event($event);
        Gate::authorize('checkIn', $eventModel->organization);

        $stored = $request->session()->get($this->sessionKey($eventModel));

        if (! is_string($stored) || ! Hash::check($request->string('code')->toString(), $stored)) {
            return back()->withErrors(['code' => 'Ce code ne correspond pas.']);
        }

        $request->session()->forget($this->sessionKey($eventModel));

        return redirect()->route('events.check-in.index', $eventModel->id);
    }

    private function sessionKey(Event $event): string
    {
        return "kiosk_code.{$event->id}";
    }

    private function event(int $id): Event
    {
        return Event::query()->with('organization')->findOrFail($id);
    }
}
