<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Event;

use App\Domain\Event\Data\EventDuplicationPart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DuplicateEventRequest extends FormRequest
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
            'new_start_at' => ['required', 'date'],
            'parts' => ['array'],
            'parts.*' => ['string', Rule::enum(EventDuplicationPart::class)],
        ];
    }

    /**
     * @return list<EventDuplicationPart>
     */
    public function parts(): array
    {
        return array_map(
            fn (string $part): EventDuplicationPart => EventDuplicationPart::from($part),
            array_values(array_unique($this->validated('parts', []))),
        );
    }
}
