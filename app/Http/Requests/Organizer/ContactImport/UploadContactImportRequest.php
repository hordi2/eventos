<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\ContactImport;

use Illuminate\Foundation\Http\FormRequest;

final class UploadContactImportRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ];
    }
}
