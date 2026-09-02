<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\SaveRegistrationDraft;
use App\Domain\Form\Actions\StartRegistrationDraft;
use App\Domain\Form\Actions\SubmitRegistration;
use App\Domain\Form\Data\AttendeeIdentity;
use App\Domain\Form\Data\EventRegistrationContext;
use App\Domain\Form\Data\RegistrationSubmissionMetadata;
use App\Domain\Form\Data\SubmitRegistrationOutcome;
use App\Domain\Form\EventFullException;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Form\OptionFullException;
use App\Domain\Form\RegistrationClosedException;
use App\Domain\Form\Support\EvaluateFormVisibility;
use App\Domain\Form\Support\IsRegistrationWindowOpen;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\SaveAnswersRequest;
use App\Http\Requests\Guest\SaveIdentityRequest;
use App\Http\Requests\Guest\VerifyEventPasswordRequest;
use App\Support\Capacity\Actions\GetRemainingCapacity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Chaque méthode dont la route inclut {token} déclare aussi $organization et
 * $event (même non utilisés dans le corps) : la résolution des paramètres
 * de route par Laravel est positionnelle dès qu'une dépendance de classe
 * (FormRequest) est mélangée aux paramètres scalaires — omettre un segment
 * décale silencieusement les suivants sur le mauvais paramètre.
 */
final class RegistrationController extends Controller
{
    public function passwordShow(Request $request): View
    {
        return view('guest.registration.password', ['event' => $this->event($request)]);
    }

    public function passwordVerify(VerifyEventPasswordRequest $request, string $organization, string $event): RedirectResponse
    {
        $eventModel = $this->event($request);

        if (! Hash::check((string) $request->input('password'), (string) $eventModel->password_hash)) {
            throw ValidationException::withMessages(['password' => 'Mot de passe incorrect.']);
        }

        $request->session()->put("guest_event_password_verified.{$eventModel->id}", true);

        return redirect()->route('guest.registration.start', [$organization, $event]);
    }

    public function start(Request $request, string $organization, string $event): View|RedirectResponse
    {
        $eventModel = $this->event($request);
        $form = $this->requirePublishedForm($eventModel);
        $this->captureAcquisitionMetadata($request);

        if (! app(IsRegistrationWindowOpen::class)->handle($this->contextFor($eventModel))) {
            return view('guest.registration.closed', ['event' => $eventModel, 'reason' => 'window']);
        }

        if ($this->isFull($eventModel)) {
            return view('guest.registration.closed', ['event' => $eventModel, 'reason' => 'full']);
        }

        $draft = app(StartRegistrationDraft::class)->handle($eventModel->organization_id, $eventModel->id, $form->current_version_id);

        return redirect()->route('guest.registration.identity.show', [$organization, $event, $draft->resume_token]);
    }

    public function identityShow(Request $request, string $organization, string $event, string $token): View
    {
        return view('guest.registration.identity', [
            'event' => $this->event($request),
            'draft' => $this->draft($token),
        ]);
    }

    public function identityStore(SaveIdentityRequest $request, string $organization, string $event, string $token): RedirectResponse
    {
        $draft = app(SaveRegistrationDraft::class)->handle($this->draft($token), identity: $request->validated());

        return redirect()->route('guest.registration.answers.show', [$organization, $event, $draft->resume_token]);
    }

    public function answersShow(Request $request, string $organization, string $event, string $token): View
    {
        $draft = $this->draft($token);
        $version = $draft->formVersion()->with(['fields.options', 'conditionalRules.targetField'])->firstOrFail();
        $visibility = app(EvaluateFormVisibility::class)->handle($version, $draft->answers ?? []);

        return view('guest.registration.answers', [
            'event' => $this->event($request),
            'draft' => $draft,
            'version' => $version,
            'visibility' => $visibility,
        ]);
    }

    public function answersStore(SaveAnswersRequest $request, string $organization, string $event, string $token): RedirectResponse
    {
        $draft = app(SaveRegistrationDraft::class)->handle($this->draft($token), answers: $request->validated());

        return redirect()->route('guest.registration.review.show', [$organization, $event, $draft->resume_token]);
    }

    public function reviewShow(Request $request, string $organization, string $event, string $token): View
    {
        $draft = $this->draft($token);
        $version = $draft->formVersion()->with(['fields.options', 'conditionalRules.targetField'])->firstOrFail();
        $visibility = app(EvaluateFormVisibility::class)->handle($version, $draft->answers ?? []);

        return view('guest.registration.review', [
            'event' => $this->event($request),
            'draft' => $draft,
            'version' => $version,
            'visibility' => $visibility,
        ]);
    }

    public function reviewConfirm(Request $request, string $organization, string $event, string $token): RedirectResponse
    {
        $eventModel = $this->event($request);
        $draft = $this->draft($token);
        $version = $draft->formVersion()->with(['fields.options', 'conditionalRules.targetField'])->firstOrFail();
        $identity = $draft->identity ?? [];

        try {
            $result = app(SubmitRegistration::class)->handle(
                $this->contextFor($eventModel),
                $version,
                new AttendeeIdentity($identity['email'] ?? '', $identity['first_name'] ?? null, $identity['last_name'] ?? null, $identity['phone'] ?? null),
                $draft->answers ?? [],
                new RegistrationSubmissionMetadata(
                    source: $request->session()->get('guest_registration_source'),
                    utm: $request->session()->get('guest_registration_utm'),
                    referrer: $request->session()->get('guest_registration_referrer'),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                    locale: $request->getPreferredLanguage(),
                ),
                "draft:{$draft->id}",
            );
        } catch (RegistrationClosedException|EventFullException|OptionFullException $e) {
            return back()->withErrors(['submission' => $e->getMessage()]);
        }

        $draft->update(['registration_id' => $result->registration->id, 'submitted_at' => now()]);

        $routeName = $result->outcome === SubmitRegistrationOutcome::DuplicateFound
            ? 'guest.registration.duplicate'
            : 'guest.registration.confirmation';

        return redirect()->route($routeName, [$organization, $event, $draft->resume_token]);
    }

    public function confirmation(Request $request, string $organization, string $event, string $token): View
    {
        $draft = $this->draft($token);

        return view('guest.registration.confirmation', [
            'event' => $this->event($request),
            'registration' => $draft->registration()->firstOrFail(),
        ]);
    }

    public function duplicate(Request $request, string $organization, string $event, string $token): View
    {
        $draft = $this->draft($token);

        return view('guest.registration.duplicate', [
            'event' => $this->event($request),
            'registration' => $draft->registration()->firstOrFail(),
        ]);
    }

    private function event(Request $request): Event
    {
        return $request->attributes->get('guestEvent');
    }

    private function draft(string $token): RegistrationDraft
    {
        return RegistrationDraft::query()->where('resume_token', $token)->firstOrFail();
    }

    private function requirePublishedForm(Event $event): Form
    {
        $form = Form::query()->where('event_id', $event->id)->firstOrFail();

        abort_if(! $form->hasPublishedVersion(), 404);

        return $form;
    }

    private function captureAcquisitionMetadata(Request $request): void
    {
        $request->session()->put('guest_registration_source', $request->query('source'));
        $request->session()->put('guest_registration_referrer', $request->header('referer'));

        $utm = array_filter($request->only(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content']));
        $request->session()->put('guest_registration_utm', $utm === [] ? null : $utm);
    }

    private function isFull(Event $event): bool
    {
        if ($event->capacity === null || $event->allow_waitlist) {
            return false;
        }

        return app(GetRemainingCapacity::class)->isFull('event', (string) $event->id, $event->capacity);
    }

    private function contextFor(Event $event): EventRegistrationContext
    {
        return new EventRegistrationContext(
            eventId: $event->id,
            organizationId: $event->organization_id,
            capacity: $event->capacity,
            allowWaitlist: $event->allow_waitlist,
            registrationOpensAt: $event->registration_opens_at,
            registrationClosesAt: $event->registration_closes_at,
            timezone: $event->timezone,
            registrationClosedMessage: $event->registration_closed_message,
        );
    }
}
