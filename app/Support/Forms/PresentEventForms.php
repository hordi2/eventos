<?php

declare(strict_types=1);

namespace App\Support\Forms;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventStatus;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormVersionStatus;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Support\EventForms;
use App\Support\Events\EventPublicLinks;

/**
 * Page « Formulaires » d'un événement : un formulaire par public (invités,
 * bénévoles, exposants…), chacun avec son lien ; celui par défaut répond au
 * lien de l'événement.
 */
final class PresentEventForms
{
    public function __construct(
        private readonly EventForms $eventForms,
        private readonly EventPublicLinks $eventPublicLinks,
    ) {}

    /**
     * @return list<array{id: int, name: string, isDefault: bool, status: string, statusLabel: string, responses: int, url: string, isLive: bool, editUrl: string, previewUrl: string, canDelete: bool}>
     */
    public function handle(Event $event): array
    {
        $responses = Registration::query()
            ->where('registrations.event_id', $event->id)
            ->whereNull('registrations.parent_registration_id')
            ->join('form_versions', 'form_versions.id', '=', 'registrations.form_version_id')
            ->groupBy('form_versions.form_id')
            ->selectRaw('form_versions.form_id, count(*) as total')
            ->pluck('total', 'form_id');

        return $this->eventForms->all($event->id)->map(function (Form $form) use ($event, $responses): array {
            $count = (int) ($responses[$form->id] ?? 0);
            [$status, $label] = $this->status($form);

            return [
                'id' => $form->id,
                'name' => $form->name,
                'isDefault' => $form->is_default,
                'status' => $status,
                'statusLabel' => $label,
                'responses' => $count,
                'url' => $this->eventPublicLinks->publicUrl($event, $form->is_default ? null : $form->slug),
                'isLive' => $event->status === EventStatus::Published && $form->hasPublishedVersion(),
                'editUrl' => route('forms.edit', $form->id),
                'previewUrl' => route('forms.preview', $form->id),
                'canDelete' => ! $form->is_default && $count === 0,
            ];
        })->values()->all();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function status(Form $form): array
    {
        if (! $form->hasPublishedVersion()) {
            return ['draft', 'Brouillon'];
        }

        return $form->latestVersion()?->status === FormVersionStatus::Published
            ? ['published', 'Publié']
            : ['changes', 'Modifications non publiées'];
    }
}
