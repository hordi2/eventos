<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Assistant;

use App\Support\Assistant\WriteMessageCopy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class WriteCopyRequest extends FormRequest
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
            'brief' => ['required', 'string', 'min:5', 'max:2000'],
            'tone' => ['required', Rule::in(array_keys(WriteMessageCopy::TONES))],
            'channel' => ['nullable', Rule::in(['email', 'whatsapp'])],
            'event_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'brief.required' => 'Dites à l’assistant ce que vous voulez dire.',
        ];
    }
}
