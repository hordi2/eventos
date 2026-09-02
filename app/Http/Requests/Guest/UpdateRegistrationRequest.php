<?php

declare(strict_types=1);

namespace App\Http\Requests\Guest;

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

        return [
            'email' => ['required', 'email:rfc'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            ...app(BuildFormValidationRules::class)->handle($version, $this->all()),
        ];
    }
}
