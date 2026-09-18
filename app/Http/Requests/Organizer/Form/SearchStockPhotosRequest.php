<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Form;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Recherche dans la bibliothèque de photos libres. Le droit (modifier les
 * événements) est porté par la route.
 */
final class SearchStockPhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'query' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
