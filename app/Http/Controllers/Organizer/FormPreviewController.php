<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormVersionStatus;
use App\Http\Controllers\Controller;
use App\Support\Registration\BuildGuestSubEventChoices;
use App\Support\Registration\PresentGuestPresentation;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Aperçu d'un formulaire tel que ses invités le verront : même mise en page,
 * même thème (bandeau, logo, couleurs, CSS personnalisé), mêmes questions —
 * celles de la dernière version, publiée ou non. Rien ne s'y enregistre.
 */
final class FormPreviewController extends Controller
{
    public function show(int $form, PresentGuestPresentation $presentGuestPresentation, BuildGuestSubEventChoices $buildGuestSubEventChoices): View
    {
        $formModel = Form::query()->findOrFail($form);
        Gate::authorize('update', $formModel);
        $event = Event::query()->findOrFail($formModel->event_id);
        $version = $formModel->latestVersion()?->load('fields.options');

        return view('guest.registration.preview', [
            'event' => $event,
            'form' => $formModel,
            'fields' => $version !== null ? $version->fields : collect(),
            'isUnpublished' => $version?->status !== FormVersionStatus::Published,
            'subEventChoices' => $buildGuestSubEventChoices->handle($event),
            ...$presentGuestPresentation->handle($event, $formModel),
        ]);
    }
}
