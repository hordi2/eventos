<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\ContactImport;

use App\Domain\Contact\Models\DuplicateStrategy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ConfirmContactImportMappingRequest extends FormRequest
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
            'mapping' => ['required', 'array'],
            'mapping.*' => ['nullable', 'string'],
            'duplicate_strategy' => ['required', Rule::enum(DuplicateStrategy::class)],
        ];
    }
}
