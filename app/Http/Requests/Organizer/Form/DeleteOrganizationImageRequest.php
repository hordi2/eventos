<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Form;

use App\Domain\Organization\Models\OrganizationImage;
use App\Support\Images\FindOrganizationImageUsage;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Suppression définitive d'une image de « Mes images ». Elle est refusée tant
 * que l'image sert quelque part : le message nomme les emplacements, pour que
 * l'organisateur sache où aller la retirer.
 */
final class DeleteOrganizationImageRequest extends FormRequest
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
        return [];
    }

    /**
     * @return list<callable>
     */
    public function after(FindOrganizationImageUsage $findOrganizationImageUsage): array
    {
        return [function (Validator $validator) use ($findOrganizationImageUsage): void {
            /** @var OrganizationImage $image */
            $image = $this->route('image');
            $usages = $findOrganizationImageUsage->handle($image);

            if ($usages !== []) {
                $validator->errors()->add('image', 'Cette image est encore utilisée ('.implode(', ', $usages).'). Retirez-la de ces emplacements avant de la supprimer.');
            }
        }];
    }
}
