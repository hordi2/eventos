<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Organization;

use App\Domain\Organization\Models\ThemeMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class UpdateBrandingRequest extends FormRequest
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
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme_mode' => ['required', new Enum(ThemeMode::class)],
        ];
    }
}
