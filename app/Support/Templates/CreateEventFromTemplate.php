<?php

declare(strict_types=1);

namespace App\Support\Templates;

use App\Domain\Event\Actions\CreateEvent;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventTemplate;
use App\Domain\Form\Actions\CreateForm;
use App\Domain\Form\Actions\PublishFormVersion;
use App\Domain\Organization\Models\Organization;
use App\Domain\Page\Actions\UpdatePage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Monter un événement à partir d'un modèle de la bibliothèque (D11).
 *
 * L'événement naît en brouillon, à une date que l'organisateur choisira : un
 * modèle donne une structure, pas un calendrier. Le formulaire et la page
 * reprennent celui et celle du modèle.
 *
 * Traverse Event, Form et Page : sa place est dans Support (section 3 du
 * CLAUDE.md).
 */
final class CreateEventFromTemplate
{
    public function __construct(
        private readonly CreateEvent $createEvent,
        private readonly CreateForm $createForm,
        private readonly PublishFormVersion $publishFormVersion,
        private readonly UpdatePage $updatePage,
    ) {}

    public function handle(EventTemplate $template, Organization $organization, User $creator, string $title, CarbonImmutable $startAt): Event
    {
        $payload = $template->payload;
        $settings = $payload['settings'] ?? [];

        return DB::transaction(function () use ($template, $organization, $creator, $title, $startAt, $payload, $settings): Event {
            $event = $this->createEvent->handle($organization, $creator, [
                'title' => $title,
                'type' => $template->category,
                'start_at' => $startAt->toDateTimeString(),
                'timezone' => $startAt->timezoneName,
                'requires_approval' => $settings['requires_approval'] ?? false,
                'allow_waitlist' => $settings['allow_waitlist'] ?? false,
                'allow_guest_edit' => $settings['allow_guest_edit'] ?? false,
                'has_attendee_directory' => $settings['has_attendee_directory'] ?? false,
                'has_carbon_report' => $settings['has_carbon_report'] ?? false,
            ]);

            $form = $this->createForm->handle($organization, $event->id, $creator, [
                'name' => 'Inscription',
                'fields' => $payload['fields'] ?? [],
            ]);
            $this->publishFormVersion->handle($form, $creator);

            if (($payload['blocks'] ?? []) !== []) {
                $this->updatePage->handle(
                    organization: $organization,
                    eventId: $event->id,
                    metaDescription: null,
                    blocks: $payload['blocks'],
                    user: $creator,
                );
            }

            // Combien de fois ce modèle a servi : c'est ce qui le fait
            // remonter dans la bibliothèque.
            $template->increment('uses_count');

            return $event;
        });
    }
}
