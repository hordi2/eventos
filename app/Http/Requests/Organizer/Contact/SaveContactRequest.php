<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Contact;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveContactRequest extends FormRequest
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
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone_e164' => ['nullable', 'string', 'max:32'],
            'company' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'preferred_language' => ['nullable', 'string', 'max:10'],
            'preferred_channel' => ['nullable', 'string', 'in:email,sms,whatsapp'],
            'household_name' => ['nullable', 'string', 'max:255'],
            'email_consent' => ['nullable', 'boolean'],
            'sms_consent' => ['nullable', 'boolean'],
            'whatsapp_consent' => ['nullable', 'boolean'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('tags', 'id')],
        ];
    }
}
