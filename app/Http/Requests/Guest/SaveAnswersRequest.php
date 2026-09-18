<?php

declare(strict_types=1);

namespace App\Http\Requests\Guest;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\SaveRegistrationDraft;
use App\Domain\Form\Actions\StoreGuestUploads;
use App\Domain\Form\Actions\SyncSubEventRegistrations;
use App\Domain\Form\Data\CompanionData;
use App\Domain\Form\Data\FormVisibilityContext;
use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Form\Support\BuildFormValidationRules;
use App\Domain\Form\Support\DonationAnswer;
use App\Domain\Form\Support\EvaluateFormVisibility;
use App\Domain\Form\Support\FileUploadAnswer;
use App\Domain\Form\Support\ValidateCompanions;
use App\Domain\Form\Support\ValidateRegistrationFiles;
use App\Support\GuestList\ResolveGuestInvitation;
use App\Support\Registration\BuildGuestVisibilityContext;
use App\Support\Registration\BuildSubEventContexts;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * Règles dynamiques : dépendent des champs réellement configurés sur le
 * formulaire de l'événement, et de la logique conditionnelle déjà remplie
 * (BuildFormValidationRules, T-022 — le même moteur que SubmitRegistration,
 * jamais une règle dupliquée ni divergente). Les accompagnants saisis à
 * l'étape précédente répondent en plus aux questions posées à chaque
 * personne (T-032), et deux sessions choisies ne peuvent pas se chevaucher
 * (T-013).
 */
final class SaveAnswersRequest extends FormRequest
{
    private ?RegistrationDraft $resolvedDraft = null;

    private ?FormVersion $resolvedVersion = null;

    /**
     * Fichiers joints refusés au contrôle, par clé de question.
     *
     * @var array<string, string>
     */
    private array $uploadErrors = [];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Les fichiers joints passent en quarantaine avant la validation : leur
     * référence prend leur place dans les réponses (StoreGuestUploads) et
     * rejoint aussitôt le brouillon. L'ancienne saisie renvoyée après une
     * erreur ne reprend pas ce qui est ajouté ici : sans le brouillon,
     * l'invité devrait renvoyer un fichier déjà accepté.
     */
    protected function prepareForValidation(): void
    {
        $uploads = $this->file(FileUploadAnswer::INPUT_KEY);

        if (! is_array($uploads)) {
            return;
        }

        $stored = app(StoreGuestUploads::class)->handle(
            $this->version(),
            $this->draft()->organization_id,
            $this->guestEvent()->id,
            $uploads,
            $this->draft()->id,
        );

        $this->uploadErrors = $stored['errors'];
        $this->merge($stored['tokens']);

        if ($stored['tokens'] !== []) {
            app(SaveRegistrationDraft::class)->handle($this->draft(), answers: $stored['tokens']);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $identity = $this->draft()->identity ?? [];
        $holderAnswers = $this->except([CompanionData::INPUT_KEY, FileUploadAnswer::INPUT_KEY]);
        $submitted = $this->input(CompanionData::INPUT_KEY);
        $companionsAnswers = [];

        foreach (array_keys($identity['companions'] ?? []) as $index) {
            $answers = is_array($submitted) ? ($submitted[$index]['answers'] ?? []) : [];
            $companionsAnswers[(int) $index] = is_array($answers) ? $answers : [];
        }

        return [
            ...app(BuildFormValidationRules::class)->handle($this->version(), $holderAnswers, $this->visibilityContext()),
            ...app(ValidateCompanions::class)->rules($this->version(), $holderAnswers, $companionsAnswers, $this->visibilityContext()),
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            // Un fichier refusé au contrôle : son message plutôt qu'un « obligatoire ».
            function (Validator $validator): void {
                foreach ($this->uploadErrors as $key => $message) {
                    $validator->errors()->forget($key);
                    $validator->errors()->add($key, $message);
                }
            },
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $answers = $this->except([CompanionData::INPUT_KEY, FileUploadAnswer::INPUT_KEY]);
                $visibility = app(EvaluateFormVisibility::class)->handle($this->version(), $answers, $this->visibilityContext());

                foreach (app(ValidateRegistrationFiles::class)->errors($this->version(), $answers, $visibility) as $key => $message) {
                    $validator->errors()->add($key, $message);
                }
            },
            // Un invité qui répond avec son seul numéro WhatsApp ne peut pas
            // régler un don en ligne : carte et Mobile Money exigent un e-mail.
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || trim((string) (($this->draft()->identity ?? [])['email'] ?? '')) !== '') {
                    return;
                }

                $answers = $this->except([CompanionData::INPUT_KEY, FileUploadAnswer::INPUT_KEY]);
                $visibility = app(EvaluateFormVisibility::class)->handle($this->version(), $answers, $this->visibilityContext());

                foreach ($this->version()->fields as $field) {
                    if ($field->type === FieldType::Donation && DonationAnswer::isGiven($this->version(), [$field->key => $answers[$field->key] ?? null], $visibility)) {
                        $validator->errors()->add($field->key, "Pour faire un don en ligne, ajoutez votre adresse e-mail à l'étape précédente : le paiement par carte ou Mobile Money l'exige.");
                    }
                }
            },
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $answers = $this->except([CompanionData::INPUT_KEY, FileUploadAnswer::INPUT_KEY]);
                $visibility = app(EvaluateFormVisibility::class)->handle($this->version(), $answers, $this->visibilityContext());
                $sync = app(SyncSubEventRegistrations::class);
                $selected = $sync->selected($this->version(), $answers, $visibility, app(BuildSubEventContexts::class)->handle($this->guestEvent()));

                try {
                    $sync->assertNoScheduleConflict($this->version(), $selected);
                } catch (ValidationException $exception) {
                    foreach ($exception->errors() as $key => $messages) {
                        $validator->errors()->add($key, $messages[0]);
                    }
                }
            },
        ];
    }

    private function draft(): RegistrationDraft
    {
        return $this->resolvedDraft ??= RegistrationDraft::query()
            ->where('resume_token', $this->route('token'))
            ->firstOrFail();
    }

    private function version(): FormVersion
    {
        return $this->resolvedVersion ??= $this->draft()->formVersion()->with(['fields.options', 'conditionalRules.targetField'])->firstOrFail();
    }

    private function visibilityContext(): FormVisibilityContext
    {
        $identity = $this->draft()->identity ?? [];

        return app(BuildGuestVisibilityContext::class)->handle(
            $this->draft()->organization_id,
            $identity['email'] ?? null,
            (bool) ($identity['attending'] ?? true),
            app(ResolveGuestInvitation::class)->handle($this->draft())?->contact->id,
        );
    }

    private function guestEvent(): Event
    {
        /** @var Event $event */
        $event = $this->attributes->get('guestEvent');

        return $event;
    }
}
