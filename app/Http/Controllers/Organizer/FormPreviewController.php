<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\StartRegistrationDraft;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Support\FormSettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * « Prévisualiser » : une simulation d'inscription dans le vrai parcours
 * invité, depuis son premier écran, avec la dernière version du formulaire
 * — publiée ou non. Rien ne s'enregistre à la fin, aucun message ne part.
 */
final class FormPreviewController extends Controller
{
    public function show(Request $request, int $form, StartRegistrationDraft $startRegistrationDraft): RedirectResponse
    {
        $formModel = Form::query()->findOrFail($form);
        Gate::authorize('update', $formModel);
        $event = Event::query()->with('organization')->findOrFail($formModel->event_id);
        $version = $formModel->latestVersion() ?? abort(404);
        $draft = $startRegistrationDraft->handle($event->organization_id, $event->id, $version->id, isTest: true);

        // L'organisateur passe les portes de son propre événement : encore « Inédit », ou protégé par mot de passe.
        $request->session()->put("guest_event_preview.{$event->id}", true);
        $request->session()->put("guest_event_password_verified.{$event->id}", true);

        $firstStep = FormSettings::resolve($formModel->settings)['welcome']['enabled'] ? 'guest.registration.welcome.show' : 'guest.registration.identity.show';

        return redirect()->route($firstStep, [$event->organization->slug, $event->slug, $draft->resume_token]);
    }
}
