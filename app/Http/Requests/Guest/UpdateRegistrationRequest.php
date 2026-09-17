<?php

declare(strict_types=1);

namespace App\Http\Requests\Guest;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventCategory;
use App\Domain\Form\Data\CompanionData;
use App\Domain\Form\Data\FormVisibilityContext;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Form\Support\BuildFormValidationRules;
use App\Domain\Form\Support\ValidateCompanions;
use App\Support\Registration\BuildGuestVisibilityContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Toujours validée contre la version du formulaire de LA Registration
 * elle-même, jamais la version actuellement publiée du formulaire (§4.7 du
 * CLAUDE.md) — voir UpdateRegistration.
 */
final class UpdateRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * La même URL signée sert à afficher le formulaire (GET) et à le
     * soumettre (POST) — ne valider que la soumission, jamais l'affichage.
     */
    public function validateResolved(): void
    {
        if ($this->isMethod('get')) {
            return;
        }

        parent::validateResolved();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $registration = Registration::query()->findOrFail($this->route('registration'));
        $version = $registration->formVersion()->with(['fields.options', 'conditionalRules.targetField'])->firstOrFail();
        // Registration ne porte jamais de relation Eloquent vers Event
        // (section 3 du CLAUDE.md) : chargé ici séparément, ce Form Request
        // n'étant pas sous Domain/Form, il peut le faire librement.
        $event = Event::query()->findOrFail($registration->event_id);

        $context = app(BuildGuestVisibilityContext::class)->handle(
            $registration->organization_id,
            $registration->email,
            $registration->status !== RegistrationStatus::Declined,
        );

        $holderAnswers = $this->except(CompanionData::INPUT_KEY);

        return [
            'email' => ['required', 'email:rfc'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => [$event->type->category() === EventCategory::Personal ? 'required' : 'nullable', 'string', 'max:32'],
            ...app(BuildFormValidationRules::class)->handle($version, $holderAnswers, $context, excludeLocked: true),
            ...$this->companionRules($registration, $version, $holderAnswers, $context),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required' => 'Le numéro de téléphone est obligatoire pour ce type d\'événement.',
            CompanionData::INPUT_KEY.'.*.first_name.required' => 'Indiquez le prénom de chaque accompagnant.',
        ];
    }

    /**
     * Les accompagnants existants gardent leur rang : leur nombre ne change
     * pas en modification (UpdateRegistration).
     *
     * @param  array<string, mixed>  $holderAnswers
     * @return array<string, list<mixed>>
     */
    private function companionRules(Registration $registration, FormVersion $version, array $holderAnswers, FormVisibilityContext $context): array
    {
        $submitted = $this->input(CompanionData::INPUT_KEY);
        $rules = [];
        $companionsAnswers = [];

        foreach (array_keys($registration->companions()->get()->all()) as $index) {
            $rules[CompanionData::INPUT_KEY.".{$index}.first_name"] = ['required', 'string', 'max:255'];
            $rules[CompanionData::INPUT_KEY.".{$index}.last_name"] = ['nullable', 'string', 'max:255'];

            $answers = is_array($submitted) ? ($submitted[$index]['answers'] ?? []) : [];
            $companionsAnswers[$index] = is_array($answers) ? $answers : [];
        }

        return [...$rules, ...app(ValidateCompanions::class)->rules($version, $holderAnswers, $companionsAnswers, $context)];
    }
}
