<?php

declare(strict_types=1);

namespace App\Http\Requests\Guest;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventCategory;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Support\BuildFormValidationRules;
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

        return [
            'email' => ['required', 'email:rfc'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => [$event->type->category() === EventCategory::Personal ? 'required' : 'nullable', 'string', 'max:32'],
            ...app(BuildFormValidationRules::class)->handle($version, $this->all()),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required' => 'Le numéro de téléphone est obligatoire pour ce type d\'événement.',
        ];
    }
}
