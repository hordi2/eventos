<?php

declare(strict_types=1);

namespace App\Http\Requests\Guest;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\SyncSubEventRegistrations;
use App\Domain\Form\Data\CompanionData;
use App\Domain\Form\Data\FormVisibilityContext;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Form\Support\BuildFormValidationRules;
use App\Domain\Form\Support\EvaluateFormVisibility;
use App\Domain\Form\Support\ValidateCompanions;
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

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $identity = $this->draft()->identity ?? [];
        $holderAnswers = $this->except(CompanionData::INPUT_KEY);
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
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $answers = $this->except(CompanionData::INPUT_KEY);
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
        );
    }

    private function guestEvent(): Event
    {
        /** @var Event $event */
        $event = $this->attributes->get('guestEvent');

        return $event;
    }
}
