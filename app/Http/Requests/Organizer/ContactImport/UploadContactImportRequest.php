<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\ContactImport;

use Illuminate\Foundation\Http\FormRequest;

final class UploadContactImportRequest extends FormRequest
{
    /**
     * Selon la version de libmagic, un .xlsx est reconnu comme tableur ou
     * comme simple archive zip : « zip » est donc admis, l'extension restant
     * exigée et le classeur vérifié à la lecture (SpreadsheetToCsv).
     *
     * @var list<string>
     */
    public const FILE_RULES = ['required', 'file', 'mimes:csv,txt,xlsx,zip', 'extensions:csv,txt,xlsx', 'max:10240'];

    /**
     * @var array<string, string>
     */
    public const FILE_MESSAGES = [
        'file.mimes' => 'Choisissez un fichier Excel (.xlsx) ou CSV (.csv).',
        'file.extensions' => 'Choisissez un fichier Excel (.xlsx) ou CSV (.csv).',
        'file.max' => 'Ce fichier dépasse 10 Mo : découpez votre liste en plusieurs fichiers.',
    ];

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
            'file' => self::FILE_RULES,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::FILE_MESSAGES;
    }
}
