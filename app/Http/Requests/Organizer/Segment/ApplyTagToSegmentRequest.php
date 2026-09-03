<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Segment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ApplyTagToSegmentRequest extends FormRequest
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
            'tag_id' => ['required', 'integer', Rule::exists('tags', 'id')],
        ];
    }
}
