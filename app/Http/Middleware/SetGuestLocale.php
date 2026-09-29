<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Event\Models\Event;
use App\Support\Guest\GuestLocales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Langue des pages invité (lot 2) : celle choisie dans le sélecteur si
 * l'invité en a choisi une, sinon celle de son navigateur quand nous la
 * parlons, sinon celle de l'événement. Placé après resolve-guest-event, qui
 * pose l'événement.
 */
final class SetGuestLocale
{
    private const SESSION_KEY = 'guest_locale';

    public function handle(Request $request, Closure $next): Response
    {
        $chosen = $request->query('lang');

        if (is_string($chosen) && GuestLocales::supports($chosen)) {
            $request->session()->put(self::SESSION_KEY, $chosen);
        }

        $event = $request->attributes->get('guestEvent');
        $remembered = $request->session()->get(self::SESSION_KEY);

        // Sans en-tête Accept-Language, Symfony répond « en » par défaut :
        // on ne devine une langue que si le navigateur l'a vraiment dite.
        $browser = $request->headers->has('Accept-Language') ? GuestLocales::preferred($request->getLanguages()) : null;

        $locale = (is_string($remembered) && GuestLocales::supports($remembered) ? $remembered : null)
            ?? $browser
            ?? ($event instanceof Event ? $event->locale : null)
            ?? (string) config('app.locale');

        App::setLocale($locale);

        return $next($request);
    }
}
