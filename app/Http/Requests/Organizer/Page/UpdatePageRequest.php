<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Page;

use App\Domain\Page\Models\PageBlockType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePageRequest extends FormRequest
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
            'meta_description' => ['nullable', 'string', 'max:160'],
            'cover_eyebrow' => ['nullable', 'string', 'max:120'],
            'cover_script' => ['nullable', 'string', 'max:120'],
            'cover_monogram' => ['nullable', 'string', 'max:12'],
            'cover_overlay' => ['nullable', 'integer', 'min:0', 'max:90'],
            'cover_cta_label' => ['nullable', 'string', 'max:60'],
            'blocks' => ['array', 'max:40'],
            'blocks.*.id' => ['nullable', 'string', 'max:64'],
            'blocks.*.type' => ['required', Rule::enum(PageBlockType::class)],
            'blocks.*.title' => ['nullable', 'string', 'max:255'],
            'blocks.*.body' => ['nullable', 'string', 'max:5000'],
            // Chemin d'une image de la bibliothèque, jamais une adresse libre.
            'blocks.*.path' => ['nullable', 'string', 'max:255'],
            'blocks.*.alt' => ['nullable', 'string', 'max:255'],
            'blocks.*.url' => ['nullable', 'string', 'max:2048'],
            'blocks.*.items' => ['array', 'max:100'],
            'blocks.*.items.*.time' => ['nullable', 'string', 'max:50'],
            'blocks.*.items.*.title' => ['nullable', 'string', 'max:255'],
            'blocks.*.items.*.description' => ['nullable', 'string', 'max:1000'],
            'blocks.*.items.*.question' => ['nullable', 'string', 'max:255'],
            'blocks.*.items.*.answer' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
