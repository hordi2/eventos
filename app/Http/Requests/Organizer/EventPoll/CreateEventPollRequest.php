<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\EventPoll;

use Illuminate\Foundation\Http\FormRequest;

final class CreateEventPollRequest extends FormRequest
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
            'question' => ['required', 'string', 'max:280'],
            'options' => ['required', 'array', 'min:2', 'max:8'],
            'options.*' => ['required', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'question.required' => 'La question est obligatoire.',
            'options.min' => 'Un sondage demande au moins :min réponses possibles.',
            'options.max' => 'Un sondage ne peut pas dépasser :max réponses possibles.',
            'options.*.required' => 'Chaque réponse possible doit être écrite.',
        ];
    }
}
