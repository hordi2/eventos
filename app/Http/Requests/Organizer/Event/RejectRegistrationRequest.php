<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Event;

use Illuminate\Foundation\Http\FormRequest;

final class RejectRegistrationRequest extends FormRequest
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
            // Motif facultatif, repris tel quel dans l'e-mail à l'invité.
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
