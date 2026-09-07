<?php

declare(strict_types=1);

namespace App\Listeners\Webhooks;

use App\Domain\Form\Events\RegistrationCancelled;
use App\Domain\Form\Events\RegistrationCreated;
use App\Domain\Form\Events\RegistrationUpdated;
use App\Domain\Form\Models\Registration;
use App\Support\Webhooks\DispatchWebhooksForEvent;
use App\Support\Webhooks\WebhookEvent;

/**
 * Traduit les événements Domain/Form en webhooks sortants, sans que ce
 * module ait à connaître l'existence des webhooks (même raisonnement que
 * LinkRegistrationToContact pour Domain/Contact).
 */
final class DispatchRegistrationWebhooks
{
    public function __construct(
        private readonly DispatchWebhooksForEvent $dispatchWebhooksForEvent,
    ) {}

    public function created(RegistrationCreated $event): void
    {
        $this->dispatch(WebhookEvent::RegistrationCreated, $event->registration);
    }

    public function updated(RegistrationUpdated $event): void
    {
        $this->dispatch(WebhookEvent::RegistrationUpdated, $event->registration);
    }

    public function cancelled(RegistrationCancelled $event): void
    {
        $this->dispatch(WebhookEvent::RegistrationCancelled, $event->registration);
    }

    private function dispatch(WebhookEvent $event, Registration $registration): void
    {
        $this->dispatchWebhooksForEvent->handle($registration->organization_id, $event, [
            'registration_id' => $registration->id,
            'event_id' => $registration->event_id,
            'status' => $registration->status->value,
            'email' => $registration->email,
            'first_name' => $registration->first_name,
            'last_name' => $registration->last_name,
            'submitted_at' => $registration->created_at?->toIso8601String(),
        ]);
    }
}
