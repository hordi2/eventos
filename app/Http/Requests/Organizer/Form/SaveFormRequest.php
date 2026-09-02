<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Form;

use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\RuleAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Utilisée à la fois pour créer et pour enregistrer un formulaire : les deux
 * actions envoient la même forme de données (nom, champs, règles), seule
 * l'action qui les reçoit diffère (CreateForm vs UpdateFormDraft/ReviseForm).
 */
final class SaveFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            'fields' => ['present', 'array'],
            'fields.*.key' => ['nullable', 'string', 'max:255'],
            'fields.*.type' => ['required', Rule::enum(FieldType::class)],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.help_text' => ['nullable', 'string'],
            'fields.*.is_required' => ['nullable', 'boolean'],
            'fields.*.config' => ['nullable', 'array'],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.options.*.value' => ['nullable', 'string', 'max:255'],
            'fields.*.options.*.label' => ['required', 'string', 'max:255'],
            'fields.*.options.*.quota' => ['nullable', 'integer', 'min:0'],

            'rules' => ['nullable', 'array'],
            'rules.*.target_field_key' => ['required', 'string'],
            'rules.*.action' => ['required', Rule::enum(RuleAction::class)],
            'rules.*.condition_group' => ['required', 'array'],
        ];
    }
}
