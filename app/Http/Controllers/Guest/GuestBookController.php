<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Domain\Event\Models\Event;
use App\Domain\Page\Actions\SignGuestBook;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\SignGuestBookRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Livre d'or vu de l'invité (D1) : il laisse un mot depuis la page
 * publique, sans compte. Débit limité, comme tout formulaire public (§7 du
 * CLAUDE.md).
 */
final class GuestBookController extends Controller
{
    public function store(SignGuestBookRequest $request, string $organization, string $event, SignGuestBook $signGuestBook): RedirectResponse
    {
        $eventModel = $this->event($request);

        $signGuestBook->handle(
            $eventModel,
            $request->string('author_name')->toString(),
            $request->string('message')->toString(),
            $request->ip(),
        );

        return back()->with('status', 'guest-book-signed');
    }

    /**
     * L'événement posé par resolve-guest-event.
     */
    private function event(Request $request): Event
    {
        $event = $request->attributes->get('guestEvent');
        abort_unless($event instanceof Event, 404);

        return $event;
    }
}
