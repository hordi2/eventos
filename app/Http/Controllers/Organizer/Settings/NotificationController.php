<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer\Settings;

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Actions\SetRegistrationNotificationPreference;
use App\Domain\Organization\Models\RegistrationNotificationPreference;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class NotificationController extends Controller
{
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $events = Event::query()->orderByDesc('start_at')->get(['id', 'title', 'start_at', 'timezone']);

        $preferences = RegistrationNotificationPreference::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('event_id');

        return Inertia::render('Settings/Notifications', [
            // ?? true défensif : create() ne relit pas les valeurs par
            // défaut de la base pour les colonnes non précisées (même
            // piège que OrganizationPolicy::check() pour
            // allow_editor_financial_access) — le défaut réel en base est
            // "activé", jamais false.
            'registrationNotificationsEnabled' => $user->registration_notifications_enabled ?? true,
            'events' => $events->map(fn (Event $event): array => [
                'id' => $event->id,
                'title' => $event->title,
                // Affichage dans le fuseau de l'événement, jamais celui du
                // serveur (règle 4.3) ; `start_at` sert au tri côté client.
                'start_at' => $event->start_at->toIso8601String(),
                'start_at_formatted' => $event->start_at->setTimezone($event->timezone)->translatedFormat('j F Y \à H\hi'),
                'notify_created' => $preferences->get($event->id)->notify_created ?? true,
                'notify_updated' => $preferences->get($event->id)->notify_updated ?? true,
                'notify_cancelled' => $preferences->get($event->id)->notify_cancelled ?? true,
            ])->all(),
        ]);
    }

    public function updateMaster(Request $request): RedirectResponse
    {
        $request->validate(['registration_notifications_enabled' => ['required', 'boolean']]);

        /** @var User $user */
        $user = $request->user();
        $user->update(['registration_notifications_enabled' => $request->boolean('registration_notifications_enabled')]);

        return back()->with('status', 'notifications-updated');
    }

    public function updateEvent(Request $request, int $event, SetRegistrationNotificationPreference $action): RedirectResponse
    {
        $request->validate([
            'notify_created' => ['required', 'boolean'],
            'notify_updated' => ['required', 'boolean'],
            'notify_cancelled' => ['required', 'boolean'],
        ]);

        $eventModel = Event::query()->findOrFail($event);

        /** @var User $user */
        $user = $request->user();

        $action->handle(
            $user,
            $eventModel,
            $request->boolean('notify_created'),
            $request->boolean('notify_updated'),
            $request->boolean('notify_cancelled'),
        );

        return back()->with('status', 'notifications-updated');
    }
}
