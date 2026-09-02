<?php

declare(strict_types=1);

namespace App\Http\Requests\Guest;

use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Form\Support\BuildFormValidationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Règles dynamiques : dépendent des champs réellement configurés sur le
 * formulaire de l'événement, et de la logique conditionnelle déjà remplie
 * (BuildFormValidationRules, T-022 — le même moteur que SubmitRegistration,
 * jamais une règle dupliquée ni divergente).
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

        return app(BuildFormValidationRules::class)->handle($version, $this->all());
    }
}
