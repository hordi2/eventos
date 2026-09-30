<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Event;

use App\Domain\Event\Models\ProposalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DecideOnProposalRequest extends FormRequest
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
            'status' => ['required', Rule::enum(ProposalStatus::class)],
            'review_note' => ['nullable', 'string', 'max:2000'],
            'decision_message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Indiquez si le sujet est retenu ou refusé.',
        ];
    }
}
