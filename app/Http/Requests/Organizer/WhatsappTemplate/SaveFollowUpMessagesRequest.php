<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\WhatsappTemplate;

use App\Domain\Messaging\Models\FollowUpChannel;
use App\Domain\Messaging\Models\FollowUpMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveFollowUpMessagesRequest extends FormRequest
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
        $rules = [
            'channel' => ['required', Rule::enum(FollowUpChannel::class)],
            'templates' => ['array'],
        ];

        foreach (FollowUpMessage::cases() as $message) {
            // La RLS rend déjà invisible le modèle d'une autre organisation.
            $rules["templates.{$message->value}"] = ['nullable', 'integer', Rule::exists('whatsapp_templates', 'id')->whereNull('deleted_at')];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'channel.required' => 'Choisissez le canal des messages de suivi.',
        ];
    }
}
