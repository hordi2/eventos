<?php

declare(strict_types=1);

namespace App\Support\Registration;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventCategory;
use App\Domain\Form\Models\Form;

/**
 * « Je ne pourrai pas être présent » fait-il partie des réponses ?
 *
 * Sur une invitation personnelle — mariage, anniversaire, baptême —, oui
 * d'office : les trois réponses sont l'objet même du carton. Ailleurs, une
 * inscription n'a pas de refus à proposer tant que l'organisateur ne l'a pas
 * demandé. Dans tous les cas, un réglage enregistré par l'organisateur
 * l'emporte sur ce choix d'origine.
 *
 * Traverse Event et Form : sa place est dans Support (section 3 du CLAUDE.md).
 */
final class DeclineAnswer
{
    public static function isOffered(Event $event, ?Form $form): bool
    {
        $stored = $form?->settings['rsvp']['decline_enabled'] ?? null;

        if (is_bool($stored)) {
            return $stored;
        }

        return $event->type->category() === EventCategory::Personal;
    }
}
