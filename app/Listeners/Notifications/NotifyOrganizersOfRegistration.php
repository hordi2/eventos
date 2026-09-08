<?php

declare(strict_types=1);

namespace App\Listeners\Notifications;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Events\RegistrationCancelled;
use App\Domain\Form\Events\RegistrationCreated;
use App\Domain\Form\Events\RegistrationUpdated;
use App\Domain\Form\Models\Registration;
use App\Domain\Organization\Models\RegistrationNotificationPreference;
use App\Mail\OrganizerRegistrationNotificationMail;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\Mail;

/**
 * Ni Domain/Form ni Domain/Organization ne doivent se référencer l'un
 * l'autre (section 3 du CLAUDE.md) : ce pont vit hors des deux, comme
 * DispatchRegistrationWebhooks. Volontairement synchrone (pas ShouldQueue,
 * même raisonnement que SendConfirmationEmail) : seul l'envoi effectif de
 * chaque e-mail est mis en file (OrganizerRegistrationNotificationMail ne
 * porte que des chaînes, sans risque de scope multi-tenant prématuré).
 */
final class NotifyOrganizersOfRegistration
{
    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
    ) {}

    public function created(RegistrationCreated $event): void
    {
        $this->notify($event->registration, 'created');
    }

    public function updated(RegistrationUpdated $event): void
    {
        $this->notify($event->registration, 'updated');
    }

    public function cancelled(RegistrationCancelled $event): void
    {
        $this->notify($event->registration, 'cancelled');
    }

    private function notify(Registration $registration, string $type): void
    {
        $this->currentOrganization->set($registration->organization_id);

        $eventModel = Event::query()->find($registration->event_id);

        if ($eventModel === null) {
            return;
        }

        $guestName = trim("{$registration->first_name} {$registration->last_name}");
        $guestName = $guestName !== '' ? $guestName : $registration->email;

        $recipients = User::query()
            ->whereHas('memberships', fn ($query) => $query->where('organization_id', $registration->organization_id))
            ->where('registration_notifications_enabled', true)
            ->get();

        $preferences = RegistrationNotificationPreference::query()
            ->where('event_id', $registration->event_id)
            ->whereIn('user_id', $recipients->pluck('id'))
            ->get()
            ->keyBy('user_id');

        foreach ($recipients as $recipient) {
            $preference = $preferences->get($recipient->id);

            $wantsThisType = match ($type) {
                'created' => $preference->notify_created ?? true,
                'updated' => $preference->notify_updated ?? true,
                'cancelled' => $preference->notify_cancelled ?? true,
                default => true,
            };

            if (! $wantsThisType) {
                continue;
            }

            Mail::to($recipient->email)->queue(new OrganizerRegistrationNotificationMail($eventModel->title, $guestName, $type));
        }
    }
}
