<?php

declare(strict_types=1);

namespace App\Http\Requests\Guest;

use App\Domain\Form\Data\CompanionData;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Form\Support\BuildFormValidationRules;
use App\Domain\Form\Support\ValidateCompanions;
use App\Support\Registration\BuildGuestVisibilityContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Règles dynamiques : dépendent des champs réellement configurés sur le
 * formulaire de l'événement, et de la logique conditionnelle déjà remplie
 * (BuildFormValidationRules, T-022 — le même moteur que SubmitRegistration,
 * jamais une règle dupliquée ni divergente). Les accompagnants saisis à
 * l'étape précédente répondent en plus aux questions posées à chaque
 * personne (T-032).
 */
final class SaveAnswersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $draft = RegistrationDraft::query()
            ->where('resume_token', $this->route('token'))
            ->firstOrFail();

        $version = $draft->formVersion()->with(['fields.options', 'conditionalRules.targetField'])->firstOrFail();
        $identity = $draft->identity ?? [];

        $context = app(BuildGuestVisibilityContext::class)->handle(
            $draft->organization_id,
            $identity['email'] ?? null,
            (bool) ($identity['attending'] ?? true),
        );

        $holderAnswers = $this->except(CompanionData::INPUT_KEY);
        $submitted = $this->input(CompanionData::INPUT_KEY);
        $companionsAnswers = [];

        foreach (array_keys($identity['companions'] ?? []) as $index) {
            $answers = is_array($submitted) ? ($submitted[$index]['answers'] ?? []) : [];
            $companionsAnswers[(int) $index] = is_array($answers) ? $answers : [];
        }

        return [
            ...app(BuildFormValidationRules::class)->handle($version, $holderAnswers, $context),
            ...app(ValidateCompanions::class)->rules($version, $holderAnswers, $companionsAnswers, $context),
        ];
    }
}
