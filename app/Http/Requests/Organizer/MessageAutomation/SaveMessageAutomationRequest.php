<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\MessageAutomation;

use App\Domain\Messaging\Models\MessageAutomationType;
use App\Domain\Messaging\Models\MessageChannel;
use App\Support\Segments\EventSegment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveMessageAutomationRequest extends FormRequest
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
            'channel' => ['required', 'string', Rule::enum(MessageChannel::class)],
            // Exactement l'un des deux, selon le canal choisi — jamais les
            // deux à la fois (voir MessageAutomationController::store()).
            'email_template_id' => [
                Rule::requiredIf(fn (): bool => $this->input('channel') === 'email'),
                'nullable', 'integer', Rule::exists('email_templates', 'id'),
            ],
            'whatsapp_template_id' => [
                Rule::requiredIf(fn (): bool => $this->input('channel') === 'whatsapp'),
                'nullable', 'integer', Rule::exists('whatsapp_templates', 'id'),
            ],
            'type' => ['required', 'string', Rule::enum(MessageAutomationType::class)],
            // Obligatoire seulement pour les deux types où l'organisateur
            // choisit lui-même l'échéance — les autres la calculent depuis
            // les dates de l'événement (voir MessageAutomationController).
            'scheduled_at' => [
                Rule::requiredIf(fn (): bool => in_array($this->input('type'), ['invitation', 'reminder_unanswered'], true)),
                'nullable', 'date', 'after:now',
            ],
            'segment' => ['nullable', 'string', Rule::enum(EventSegment::class)],
        ];
    }
}
