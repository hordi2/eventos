<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\EmailAutomation;

use App\Domain\Messaging\Models\EmailAutomationType;
use App\Support\Segments\EventSegment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveEmailAutomationRequest extends FormRequest
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
            'email_template_id' => ['required', 'integer', Rule::exists('email_templates', 'id')],
            'type' => ['required', 'string', Rule::enum(EmailAutomationType::class)],
            // Obligatoire seulement pour les deux types où l'organisateur
            // choisit lui-même l'échéance — les autres la calculent depuis
            // les dates de l'événement (voir EmailAutomationController).
            'scheduled_at' => [
                Rule::requiredIf(fn (): bool => in_array($this->input('type'), ['invitation', 'reminder_unanswered'], true)),
                'nullable', 'date', 'after:now',
            ],
            'segment' => ['nullable', 'string', Rule::enum(EventSegment::class)],
        ];
    }
}
