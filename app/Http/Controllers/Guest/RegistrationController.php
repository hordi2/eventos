<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\CancelRegistration;
use App\Domain\Form\Actions\SaveRegistrationDraft;
use App\Domain\Form\Actions\StartRegistrationDraft;
use App\Domain\Form\Actions\SubmitRegistration;
use App\Domain\Form\Actions\UpdateRegistration;
use App\Domain\Form\Data\AttendeeIdentity;
use App\Domain\Form\Data\CompanionData;
use App\Domain\Form\Data\EventEditPolicy;
use App\Domain\Form\Data\EventRegistrationContext;
use App\Domain\Form\Data\FormVisibilityContext;
use App\Domain\Form\Data\RegistrationSubmissionMetadata;
use App\Domain\Form\Data\SubmitRegistrationOutcome;
use App\Domain\Form\EventFullException;
use App\Domain\Form\Models\Attendee;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationAnswer;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Form\OptionFullException;
use App\Domain\Form\RegistrationClosedException;
use App\Domain\Form\SubEventFullException;
use App\Domain\Form\Support\AskScope;
use App\Domain\Form\Support\EvaluateFormVisibility;
use App\Domain\Form\Support\FileUploadAnswer;
use App\Domain\Form\Support\FormSettings;
use App\Domain\Form\Support\IsRegistrationWindowOpen;
use App\Domain\Form\Support\PresentRegistrationFiles;
use App\Domain\Organization\Actions\GetEffectivePlan;
use App\Domain\Organization\Actions\GetPlanQuotas;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\SaveAnswersRequest;
use App\Http\Requests\Guest\SaveIdentityRequest;
use App\Http\Requests\Guest\UpdateRegistrationRequest;
use App\Http\Requests\Guest\VerifyEventPasswordRequest;
use App\Support\Capacity\Actions\GetRemainingCapacity;
use App\Support\Page\GetEventPage;
use App\Support\Registration\BuildGuestSubEventChoices;
use App\Support\Registration\BuildGuestVisibilityContext;
use App\Support\Registration\BuildSubEventContexts;
use App\Support\Registration\OpenRegistrationDonation;
use App\Support\Registration\PresentRegistrationDonation;
use App\Support\Registration\RenderAttendeeQrCodes;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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

    /**
     * Page événement publique (T-072) : bannière, description, programme,
     * lieu + carte, FAQ. Remplace l'ancien comportement de cette route, qui
     * créait immédiatement un brouillon et redirigeait vers le formulaire
     * — ce démarrage vit maintenant dans begin(), déclenché par le bouton
     * « S'inscrire » de cette page plutôt qu'à la simple visite de l'URL.
     */
    public function start(Request $request, string $organization, string $event): View
    {
        $eventModel = $this->event($request);
        $this->requirePublishedForm($eventModel);

        if (! app(IsRegistrationWindowOpen::class)->handle($this->contextFor($eventModel))) {
            return view('guest.registration.closed', ['event' => $eventModel, 'reason' => 'window']);
        }

        if ($this->isFull($eventModel)) {
            return view('guest.registration.closed', ['event' => $eventModel, 'reason' => 'full']);
        }

        return view('guest.event-page', [
            'event' => $eventModel,
            'page' => app(GetEventPage::class)->handle($eventModel),
            'beginUrl' => route('guest.registration.begin', [$organization, $event]),
        ]);
    }

    public function begin(Request $request, string $organization, string $event): View|RedirectResponse
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

        $firstStep = FormSettings::resolve($form->settings)['welcome']['enabled']
            ? 'guest.registration.welcome.show'
            : 'guest.registration.identity.show';

        return redirect()->route($firstStep, [$organization, $event, $draft->resume_token]);
    }

    /**
     * Écran « Message de bienvenue » du constructeur, avant l'identité.
     */
    public function welcomeShow(Request $request, string $organization, string $event, string $token): View
    {
        $eventModel = $this->event($request);

        return view('guest.registration.welcome', [
            'event' => $eventModel,
            'draft' => $this->draft($token),
            ...$this->presentation($eventModel),
        ]);
    }

    public function identityShow(Request $request, string $organization, string $event, string $token): View
    {
        $eventModel = $this->event($request);

        return view('guest.registration.identity', [
            'event' => $eventModel,
            'draft' => $this->draft($token),
            ...$this->presentation($eventModel),
        ]);
    }

    public function identityStore(SaveIdentityRequest $request, string $organization, string $event, string $token): RedirectResponse
    {
        $declineEnabled = (bool) $this->presentation($this->event($request))['settings']['rsvp']['decline_enabled'];
        // Sans réponse « Je ne peux pas venir » proposée, tout invité vient ;
        // un invité qui décline ne vient avec personne.
        $attending = ! $declineEnabled || $request->boolean('attending');

        $identity = [
            ...$request->safe()->except(['attending', CompanionData::INPUT_KEY]),
            'attending' => $attending,
            'companions' => $attending ? array_values($request->validated(CompanionData::INPUT_KEY, [])) : [],
        ];

        $draft = app(SaveRegistrationDraft::class)->handle($this->draft($token), identity: $identity);

        return redirect()->route('guest.registration.answers.show', [$organization, $event, $draft->resume_token]);
    }

    public function answersShow(Request $request, string $organization, string $event, string $token): View
    {
        $eventModel = $this->event($request);
        $draft = $this->draft($token);
        $version = $this->versionFor($draft);

        return view('guest.registration.answers', [
            'event' => $eventModel,
            'draft' => $draft,
            'version' => $version,
            'visibility' => app(EvaluateFormVisibility::class)->handle($version, $this->holderAnswers($draft), $this->visibilityContext($draft)),
            'companions' => $this->draftCompanions($draft, $version),
            'subEventChoices' => app(BuildGuestSubEventChoices::class)->handle($eventModel),
            'uploadedFiles' => app(PresentRegistrationFiles::class)->handle($this->holderAnswers($draft)),
            ...$this->presentation($eventModel),
        ]);
    }

    public function answersStore(SaveAnswersRequest $request, string $organization, string $event, string $token): RedirectResponse
    {
        $draft = app(SaveRegistrationDraft::class)->handle($this->draft($token), answers: $request->validated());

        return redirect()->route('guest.registration.review.show', [$organization, $event, $draft->resume_token]);
    }

    public function reviewShow(Request $request, string $organization, string $event, string $token): View
    {
        $eventModel = $this->event($request);
        $draft = $this->draft($token);
        $version = $this->versionFor($draft);

        return view('guest.registration.review', [
            'event' => $eventModel,
            'draft' => $draft,
            'version' => $version,
            'visibility' => app(EvaluateFormVisibility::class)->handle($version, $this->holderAnswers($draft), $this->visibilityContext($draft)),
            'attending' => $this->attending($draft),
            'companions' => $this->draftCompanions($draft, $version),
            'uploadedFiles' => app(PresentRegistrationFiles::class)->handle($this->holderAnswers($draft)),
            ...$this->presentation($eventModel),
        ]);
    }

    public function reviewConfirm(Request $request, string $organization, string $event, string $token): RedirectResponse
    {
        $eventModel = $this->event($request);
        $draft = $this->draft($token);
        $version = $this->versionFor($draft);
        $identity = $draft->identity ?? [];
        $companions = array_map(
            fn (array $companion): CompanionData => new CompanionData($companion['firstName'], $companion['lastName'], $companion['answers']),
            $this->draftCompanions($draft, $version),
        );

        try {
            $result = app(SubmitRegistration::class)->handle(
                $this->contextFor($eventModel),
                $version,
                new AttendeeIdentity($identity['email'] ?? '', $identity['first_name'] ?? null, $identity['last_name'] ?? null, $identity['phone'] ?? null),
                $this->holderAnswers($draft),
                new RegistrationSubmissionMetadata(
                    source: $request->session()->get('guest_registration_source'),
                    utm: $request->session()->get('guest_registration_utm'),
                    referrer: $request->session()->get('guest_registration_referrer'),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                    locale: $request->getPreferredLanguage(),
                ),
                "draft:{$draft->id}",
                $this->visibilityContext($draft),
                $companions,
            );
        } catch (RegistrationClosedException|EventFullException|OptionFullException|SubEventFullException $e) {
            return back()->withErrors(['submission' => $e->getMessage()]);
        }

        $draft->update(['registration_id' => $result->registration->id, 'submitted_at' => now()]);

        if ($result->outcome === SubmitRegistrationOutcome::DuplicateFound) {
            return redirect()->route('guest.registration.duplicate', [$organization, $event, $draft->resume_token]);
        }

        // Don promis dans le formulaire : une commande à régler (T-056).
        app(OpenRegistrationDonation::class)->handle($eventModel, $result->registration);

        return redirect()->route('guest.registration.confirmation', [$organization, $event, $draft->resume_token]);
    }

    public function confirmation(Request $request, string $organization, string $event, string $token): View
    {
        $draft = $this->draft($token);
        $eventModel = $this->event($request);
        $registration = $draft->registration()->firstOrFail();
        $policy = $this->editPolicyFor($eventModel);
        // Un refus n'a ni inscription à modifier ni place à libérer.
        $hideLinks = $policy->isLocked() || $registration->status === RegistrationStatus::Declined;

        return view('guest.registration.confirmation', [
            'event' => $eventModel,
            'registration' => $registration,
            'editUrl' => $hideLinks ? null : $this->signedEditUrl($organization, $event, $eventModel, $registration),
            'cancelUrl' => $hideLinks ? null : $this->signedCancelUrl($organization, $event, $eventModel, $registration),
            'qrCodes' => app(RenderAttendeeQrCodes::class)->handle($eventModel, $registration),
            'donation' => app(PresentRegistrationDonation::class)->handle($organization, $event, $registration),
            ...$this->presentation($eventModel),
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

    public function edit(UpdateRegistrationRequest $request, string $organization, string $event, int $registration): View|RedirectResponse
    {
        $eventModel = $this->event($request);
        $registrationModel = $this->registrationFor($eventModel, $registration);
        $policy = $this->editPolicyFor($eventModel);

        if ($policy->isLocked()) {
            return view('guest.registration.edit-locked', ['event' => $eventModel]);
        }

        if ($request->isMethod('post')) {
            return $this->applyEdit($request, $eventModel, $registrationModel, $policy);
        }

        $version = $registrationModel->formVersion()->with(['fields.options', 'conditionalRules.targetField'])->firstOrFail();
        $answers = $this->displayAnswers($registrationModel->answers()->with('formField')->get()->all());
        $context = $this->registrationVisibilityContext($registrationModel);

        return view('guest.registration.edit', [
            'event' => $eventModel,
            'registration' => $registrationModel,
            'version' => $version,
            'visibility' => app(EvaluateFormVisibility::class)->handle($version, $answers, $context),
            'answers' => $answers,
            'companions' => $this->registrationCompanions($registrationModel, $version, $answers, $context),
            'subEventChoices' => app(BuildGuestSubEventChoices::class)->handle($eventModel),
            'uploadedFiles' => app(PresentRegistrationFiles::class)->handle($answers),
            ...$this->presentation($eventModel),
        ]);
    }

    public function cancel(Request $request, string $organization, string $event, int $registration): View
    {
        $eventModel = $this->event($request);
        $registrationModel = $this->registrationFor($eventModel, $registration);
        $policy = $this->editPolicyFor($eventModel);

        if ($policy->isLocked()) {
            return view('guest.registration.edit-locked', ['event' => $eventModel]);
        }

        if ($request->isMethod('post')) {
            app(CancelRegistration::class)->handle($registrationModel, $policy, (string) $request->input('reason') ?: null);

            return view('guest.registration.cancel-success', ['event' => $eventModel]);
        }

        return view('guest.registration.cancel', ['event' => $eventModel, 'registration' => $registrationModel]);
    }

    private function applyEdit(UpdateRegistrationRequest $request, Event $event, Registration $registration, EventEditPolicy $policy): View|RedirectResponse
    {
        $data = $request->validated();
        $submitted = $data[CompanionData::INPUT_KEY] ?? [];

        $companions = $registration->companions()->get()->values()->map(
            fn (Attendee $companion, int $index): CompanionData => new CompanionData(
                firstName: (string) ($submitted[$index]['first_name'] ?? $companion->first_name),
                lastName: $submitted[$index]['last_name'] ?? null,
                answers: $submitted[$index]['answers'] ?? [],
                attendeeId: $companion->id,
            ),
        )->all();

        try {
            app(UpdateRegistration::class)->handle(
                $registration,
                $policy,
                new AttendeeIdentity($data['email'], $data['first_name'] ?? null, $data['last_name'] ?? null, $data['phone'] ?? null),
                Arr::except($data, CompanionData::INPUT_KEY),
                $this->registrationVisibilityContext($registration),
                $companions,
                app(BuildSubEventContexts::class)->handle($event),
            );
        } catch (OptionFullException|SubEventFullException $e) {
            return back()->withErrors(['submission' => $e->getMessage()]);
        }

        return view('guest.registration.edit-success', ['event' => $event]);
    }

    /**
     * Réglages d'écrans et thème du formulaire de l'événement, pour les vues
     * du parcours et la mise en page invitée.
     *
     * @return array{settings: array<string, array<string, mixed>>, formTheme: array{variables: array<string, string>, logoUrl: ?string, backgroundUrl: ?string}}
     */
    private function presentation(Event $event): array
    {
        $form = Form::query()->where('event_id', $event->id)->first();
        $settings = FormSettings::resolve($form?->settings);
        $theme = $settings['theme'];

        return [
            'settings' => $settings,
            'formTheme' => [
                'variables' => FormSettings::cssVariables($settings),
                'logoUrl' => is_string($theme['logo_path']) ? Storage::disk('public')->url($theme['logo_path']) : null,
                'backgroundUrl' => is_string($theme['background_image_path']) ? Storage::disk('public')->url($theme['background_image_path']) : null,
            ],
        ];
    }

    private function versionFor(RegistrationDraft $draft): FormVersion
    {
        return $draft->formVersion()->with(['fields.options', 'conditionalRules.targetField'])->firstOrFail();
    }

    private function attending(RegistrationDraft $draft): bool
    {
        return (bool) (($draft->identity ?? [])['attending'] ?? true);
    }

    /**
     * Réponses du titulaire : le brouillon range celles des accompagnants à part.
     *
     * @return array<string, mixed>
     */
    private function holderAnswers(RegistrationDraft $draft): array
    {
        return Arr::except($draft->answers ?? [], CompanionData::INPUT_KEY);
    }

    /**
     * Accompagnants saisis à l'étape « Coordonnées », avec leurs réponses du
     * brouillon et ce que la logique conditionnelle leur montre.
     *
     * @return list<array{firstName: string, lastName: ?string, name: string, answers: array<string, mixed>, visibility: array<string, array{visible: bool, required: bool}>}>
     */
    private function draftCompanions(RegistrationDraft $draft, FormVersion $version): array
    {
        $holderAnswers = $this->holderAnswers($draft);
        $context = $this->visibilityContext($draft);
        $companions = [];

        foreach (array_values(($draft->identity ?? [])['companions'] ?? []) as $index => $companion) {
            $answers = data_get($draft->answers, CompanionData::INPUT_KEY.".{$index}.answers", []);
            $answers = is_array($answers) ? $answers : [];
            $firstName = trim((string) ($companion['first_name'] ?? ''));
            $lastName = isset($companion['last_name']) ? trim((string) $companion['last_name']) : null;

            $companions[] = [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'name' => trim("{$firstName} {$lastName}"),
                'answers' => $answers,
                'visibility' => app(EvaluateFormVisibility::class)->handle($version, AskScope::answersFor($version, $holderAnswers, $answers), $context),
            ];
        }

        return $companions;
    }

    /**
     * @param  array<string, mixed>  $holderAnswers
     * @return list<array{attendeeId: int, firstName: string, lastName: ?string, answers: array<string, mixed>, visibility: array<string, array{visible: bool, required: bool}>}>
     */
    private function registrationCompanions(Registration $registration, FormVersion $version, array $holderAnswers, FormVisibilityContext $context): array
    {
        return $registration->companions()->get()->values()->map(function (Attendee $companion) use ($version, $holderAnswers, $context): array {
            $answers = $this->displayAnswers(
                RegistrationAnswer::query()->where('attendee_id', $companion->id)->with('formField')->get()->all(),
            );

            return [
                'attendeeId' => $companion->id,
                'firstName' => (string) $companion->first_name,
                'lastName' => $companion->last_name,
                'answers' => $answers,
                'visibility' => app(EvaluateFormVisibility::class)->handle($version, AskScope::answersFor($version, $holderAnswers, $answers), $context),
            ];
        })->all();
    }

    /**
     * @param  list<RegistrationAnswer>  $answers
     * @return array<string, mixed>
     */
    private function displayAnswers(array $answers): array
    {
        $display = [];

        foreach ($answers as $answer) {
            $display[$answer->formField->key] = $this->denormalizeForDisplay($answer->formField->type->value, $answer->value);
        }

        return $display;
    }

    private function visibilityContext(RegistrationDraft $draft): FormVisibilityContext
    {
        return app(BuildGuestVisibilityContext::class)->handle(
            $draft->organization_id,
            ($draft->identity ?? [])['email'] ?? null,
            $this->attending($draft),
        );
    }

    private function registrationVisibilityContext(Registration $registration): FormVisibilityContext
    {
        return app(BuildGuestVisibilityContext::class)->handle(
            $registration->organization_id,
            $registration->email,
            $registration->status !== RegistrationStatus::Declined,
        );
    }

    private function registrationFor(Event $event, int $registrationId): Registration
    {
        return Registration::query()->where('event_id', $event->id)->findOrFail($registrationId);
    }

    private function editPolicyFor(Event $event): EventEditPolicy
    {
        return new EventEditPolicy($event->allow_guest_edit, $event->edit_deadline, $event->timezone);
    }

    private function signedEditUrl(string $organization, string $event, Event $eventModel, Registration $registration): string
    {
        return URL::temporarySignedRoute('guest.registration.edit', $this->linkExpiry($eventModel), [$organization, $event, $registration->id]);
    }

    private function signedCancelUrl(string $organization, string $event, Event $eventModel, Registration $registration): string
    {
        return URL::temporarySignedRoute('guest.registration.cancel', $this->linkExpiry($eventModel), [$organization, $event, $registration->id]);
    }

    private function linkExpiry(Event $event): DateTimeInterface
    {
        return $event->edit_deadline ?? now()->addYear();
    }

    /**
     * Reconvertit une valeur normalisée et stockée (NormalizeFieldAnswer,
     * T-021) vers la forme attendue par un champ de formulaire HTML, pour
     * préremplir le formulaire de modification.
     */
    private function denormalizeForDisplay(string $type, mixed $value): mixed
    {
        return match ($type) {
            'yes_no' => $value ? '1' : '0',
            'date' => $value ? CarbonImmutable::parse($value)->format('Y-m-d') : $value,
            'date_time' => $value ? CarbonImmutable::parse($value)->format('Y-m-d\TH:i') : $value,
            'file_upload' => FileUploadAnswer::storedToken($value),
            default => $value,
        };
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
        $event->loadMissing('organization');
        $plan = app(GetEffectivePlan::class)->handle($event->organization);
        $quotas = app(GetPlanQuotas::class)->handle($plan);

        return new EventRegistrationContext(
            eventId: $event->id,
            organizationId: $event->organization_id,
            capacity: $event->capacity,
            allowWaitlist: $event->allow_waitlist,
            registrationOpensAt: $event->registration_opens_at,
            registrationClosesAt: $event->registration_closes_at,
            timezone: $event->timezone,
            registrationClosedMessage: $event->registration_closed_message,
            organizationMonthlyRegistrationQuota: $quotas->registrationsPerMonth,
            subEvents: app(BuildSubEventContexts::class)->handle($event),
        );
    }
}
