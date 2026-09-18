<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\GuestList;

use App\Domain\Organization\Models\Organization;
use App\Http\Requests\Organizer\ContactImport\UploadContactImportRequest;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Même fichier qu'un import de contacts, et l'accord d'envoi en plus : on
 * n'importe pas une liste d'invités sans s'être engagé sur son origine.
 */
final class UploadEventGuestImportRequest extends FormRequest
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
        return ['file' => UploadContactImportRequest::FILE_RULES];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return UploadContactImportRequest::FILE_MESSAGES;
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $organization = Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId());

            if ($organization->sender_agreement_accepted_at === null) {
                $validator->errors()->add('agreement', "Acceptez d'abord l'accord d'envoi pour importer des invités.");
            }
        }];
    }
}
