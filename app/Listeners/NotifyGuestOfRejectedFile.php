<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Data\EventEditPolicy;
use App\Domain\Form\Events\RegistrationFileRejected;
use App\Domain\Form\Models\FormField;
use App\Domain\Form\Models\Registration;
use App\Mail\RejectedRegistrationFileMail;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Fichier joint refusé par l'antivirus après la confirmation de
 * l'inscription (décision produit : analyse en arrière-plan). L'invité est
 * prévenu par e-mail, avec son lien de modification pour en envoyer un autre
 * quand l'événement le permet encore. Refusé avant la confirmation, le
 * récapitulatif le signale déjà : aucun e-mail. Traverse Form et Event,
 * d'où sa place hors de Domain.
 */
final class NotifyGuestOfRejectedFile
{
    public function handle(RegistrationFileRejected $rejected): void
    {
        $file = $rejected->file;
        $registration = $file->registration_id !== null ? Registration::query()->find($file->registration_id) : null;

        if ($registration === null || $registration->email === '') {
            return;
        }

        $event = Event::query()->with('organization')->findOrFail($file->event_id);
        $policy = new EventEditPolicy($event->allow_guest_edit, $event->edit_deadline, $event->timezone);

        $editUrl = $policy->isLocked() ? null : URL::temporarySignedRoute(
            'guest.registration.edit',
            $event->edit_deadline ?? CarbonImmutable::now()->addYear(),
            [$event->organization->slug, $event->slug, $registration->id],
        );

        Mail::to($registration->email)->queue(new RejectedRegistrationFileMail(
            organizationName: $event->organization->name,
            eventTitle: $event->title,
            fileName: $file->original_name,
            question: (string) FormField::query()->whereKey($file->form_field_id)->value('label'),
            editUrl: $editUrl,
        ));
    }
}
