<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Actions\PublishEvent;
use App\Domain\Event\Actions\UnpublishEvent;
use App\Domain\Event\InvalidEventTransitionException;
use App\Domain\Event\Models\Event;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Menu « Inédit / Publié » de la barre du haut d'un événement.
 */
final class EventPublicationController extends Controller
{
    public function publish(Request $request, int $event, PublishEvent $publishEvent): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $publishEvent->handle(Event::query()->findOrFail($event), $user);
        } catch (InvalidEventTransitionException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return back()->with('status', 'event-published');
    }

    public function unpublish(Request $request, int $event, UnpublishEvent $unpublishEvent): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $unpublishEvent->handle(Event::query()->findOrFail($event), $user);
        } catch (InvalidEventTransitionException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return back()->with('status', 'event-unpublished');
    }
}
