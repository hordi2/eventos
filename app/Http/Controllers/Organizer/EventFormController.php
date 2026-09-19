<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\DeleteForm;
use App\Domain\Form\Actions\SetDefaultForm;
use App\Domain\Form\FormCannotBeDeletedException;
use App\Domain\Form\Models\Form;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Forms\PresentEventForms;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Menu « Formulaires » d'un événement : la liste, le choix du formulaire
 * par défaut et la suppression d'un formulaire superflu.
 */
final class EventFormController extends Controller
{
    public function index(int $event, PresentEventForms $presentEventForms): Response
    {
        $eventModel = Event::query()->findOrFail($event);
        Gate::authorize('update', $eventModel);

        return Inertia::render('Forms/Index', [
            'event' => ['id' => $eventModel->id, 'title' => $eventModel->title],
            'forms' => $presentEventForms->handle($eventModel),
            'canCreate' => Gate::allows('create', [Form::class, $eventModel->organization]),
        ]);
    }

    public function makeDefault(Request $request, int $form, SetDefaultForm $setDefaultForm): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $setDefaultForm->handle(Form::query()->findOrFail($form), $user);

        return back()->with('status', 'form-made-default');
    }

    public function destroy(Request $request, int $form, DeleteForm $deleteForm): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $deleteForm->handle(Form::query()->findOrFail($form), $user);
        } catch (FormCannotBeDeletedException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        }

        return back()->with('status', 'form-deleted');
    }
}
