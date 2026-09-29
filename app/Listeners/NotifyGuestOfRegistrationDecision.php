<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Events\RegistrationApproved;
use App\Domain\Form\Events\RegistrationRejected;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Messaging\Models\MessageAutomation;
use App\Domain\Messaging\Models\MessageAutomationStatus;
use App\Domain\Messaging\Models\MessageAutomationType;
use App\Domain\Messaging\Models\MessageChannel;
use App\Mail\RegistrationDecisionMail;
use Illuminate\Support\Facades\Mail;

/**
 * Validation manuelle (M1.2) : l'invité doit savoir ce que l'organisateur a
 * décidé, même s'il a quitté la page. L'acceptation n'envoie ce message que
 * si aucune confirmation automatique par e-mail n'est en place — sinon
 * c'est elle qui parle, avec les mots de l'organisateur
 * (SendConfirmationEmail). Traverse Form, Event et Messaging, d'où sa place
 * hors de Domain.
 */
final class NotifyGuestOfRegistrationDecision
{
    public function approved(RegistrationApproved $approved): void
    {
        if ($this->hasEmailConfirmation($approved->registration)) {
            return;
        }

        $this->notify($approved->registration, true);
    }

    public function rejected(RegistrationRejected $rejected): void
    {
        $this->notify($rejected->registration, false, $rejected->registration->cancellation_reason);
    }

    private function notify(Registration $registration, bool $approved, ?string $reason = null): void
    {
        if ($registration->email === '') {
            return;
        }

        $event = Event::query()->with('organization')->findOrFail($registration->event_id);

        Mail::to($registration->email)->queue(new RegistrationDecisionMail(
            approved: $approved,
            organizationName: $event->organization->name,
            eventTitle: $event->title,
            reason: $reason,
            confirmationUrl: $approved ? $this->confirmationUrl($event, $registration) : null,
        ));
    }

    private function hasEmailConfirmation(Registration $registration): bool
    {
        return MessageAutomation::query()
            ->where('event_id', $registration->event_id)
            ->where('type', MessageAutomationType::Confirmation)
            ->where('status', MessageAutomationStatus::Active)
            ->where('channel', MessageChannel::Email)
            ->exists() && $registration->contact_id !== null;
    }

    /**
     * La page de confirmation de l'invité, avec ses QR codes : elle s'ouvre
     * par le jeton de reprise de son brouillon, jamais devinable.
     */
    private function confirmationUrl(Event $event, Registration $registration): ?string
    {
        $token = RegistrationDraft::query()->where('registration_id', $registration->id)->value('resume_token');

        return $token === null ? null : route('guest.registration.confirmation', [$event->organization->slug, $event->slug, $token]);
    }
}
