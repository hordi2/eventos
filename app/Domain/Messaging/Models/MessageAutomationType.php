<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Models;

use App\Support\Segments\EventSegment;

/**
 * Les 6 automatisations du CDC (M4.3, T-045). "confirmation" est
 * déclenchée par inscription (Registration créée), jamais par une date —
 * voir App\Listeners\SendConfirmation* (un listener par canal). Les cinq
 * autres sont des envois planifiés (App\Jobs\Send*AutomationJob, un job
 * par canal — voir MessageChannel).
 */
enum MessageAutomationType: string
{
    case Invitation = 'invitation';
    case Confirmation = 'confirmation';
    case ReminderUnanswered = 'reminder_unanswered';
    case ReminderJ7 = 'reminder_j7';
    case ReminderJ1 = 'reminder_j1';
    case ThankYou = 'thank_you';

    public function label(): string
    {
        return match ($this) {
            self::Invitation => 'Invitation',
            self::Confirmation => 'Confirmation automatique',
            self::ReminderUnanswered => 'Rappel aux non-répondants',
            self::ReminderJ7 => 'Rappel J-7',
            self::ReminderJ1 => 'Rappel J-1',
            self::ThankYou => 'Remerciement J+1',
        };
    }

    public function isScheduledByDate(): bool
    {
        return $this !== self::Confirmation;
    }

    /**
     * Un envoi (invitation, ICS joint) autant qu'une confirmation
     * marquent le début du parcours de l'invité : les deux méritent le
     * fichier ICS. Les rappels/remerciements ne le rejoignent pas — déjà
     * reçu à la confirmation. Concept propre au canal e-mail : un modèle
     * WhatsApp déjà approuvé (accord explicite) ne peut pas joindre de
     * pièce jointe libre — seul App\Jobs\SendEmailAutomationJob consulte
     * cette méthode.
     */
    public function includesIcsAttachment(): bool
    {
        return $this === self::Invitation || $this === self::Confirmation;
    }

    /**
     * Segment forcé pour les automatisations où la cible ne se discute
     * pas (M4.3 : « rappel de réponse » cible les non-répondants,
     * "remerciement" cible les présents). Invitation/confirmation
     * n'ont pas de segment forcé : la première cible qui l'organisateur
     * choisit, la seconde le contact de l'inscription elle-même.
     */
    public function forcedSegment(): ?EventSegment
    {
        return match ($this) {
            self::ReminderUnanswered => EventSegment::SansReponse,
            self::ReminderJ7, self::ReminderJ1 => EventSegment::Confirmes,
            self::ThankYou => EventSegment::Presents,
            default => null,
        };
    }
}
