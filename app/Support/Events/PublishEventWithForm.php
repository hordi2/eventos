<?php

declare(strict_types=1);

namespace App\Support\Events;

use App\Domain\Event\Actions\PublishEvent;
use App\Domain\Event\InvalidEventTransitionException;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Support\EventForms;
use App\Models\User;

/**
 * Publication d'un événement (M1.2 du CDC) : refusée tant que son formulaire
 * par défaut — celui qu'ouvre le lien de l'événement — n'est pas publié,
 * sans quoi l'invité tomberait sur une page introuvable. Vit dans Support
 * car elle lit Domain/Form avant d'agir sur Domain/Event.
 */
final class PublishEventWithForm
{
    public function __construct(
        private readonly PublishEvent $publishEvent,
        private readonly EventForms $eventForms,
    ) {}

    public function handle(Event $event, User $publisher): Event
    {
        // Une session s'inscrit par le formulaire de l'événement principal.
        if (! $event->isSubEvent()) {
            $form = $this->eventForms->forLink($event->id, null);

            if ($form === null || ! $form->hasPublishedVersion()) {
                throw InvalidEventTransitionException::missingPublishedForm($form?->name);
            }
        }

        return $this->publishEvent->handle($event, $publisher);
    }
}
