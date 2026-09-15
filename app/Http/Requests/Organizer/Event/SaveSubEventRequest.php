<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Event;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Création comme modification d'un événement secondaire (T-013). La fin est
 * obligatoire : c'est elle qui permet de détecter les chevauchements entre
 * sessions. Les dates sont saisies dans le fuseau de l'événement principal.
 */
final class SaveSubEventRequest extends FormRequest
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
        return [
            'title' => ['required', 'string', 'max:255'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'allow_waitlist' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Donnez un nom à la session.',
            'start_at.required' => 'Indiquez le début de la session.',
            'end_at.required' => 'Indiquez la fin de la session.',
            'end_at.after' => 'La fin de la session doit suivre son début.',
            'capacity.min' => 'La capacité doit être d\'au moins 1 place, ou laissée vide pour une capacité illimitée.',
        ];
    }
}
