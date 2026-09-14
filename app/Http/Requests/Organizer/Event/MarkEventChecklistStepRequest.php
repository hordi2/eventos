<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Event;

use App\Domain\Event\Models\EventChecklistStep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * L'autorisation (updateEvents) est vérifiée par MarkEventChecklistStep.
 */
final class MarkEventChecklistStepRequest extends FormRequest
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
        $markable = array_values(array_filter(
            EventChecklistStep::cases(),
            fn (EventChecklistStep $step): bool => $step->canBeMarkedManually(),
        ));

        return [
            'step' => ['required', 'string', Rule::in(array_map(fn (EventChecklistStep $step): string => $step->value, $markable))],
            'completed' => ['required', 'boolean'],
        ];
    }

    public function step(): EventChecklistStep
    {
        return EventChecklistStep::from($this->string('step')->toString());
    }
}
