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
    private ?OrganizationImage $image = null;

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
     * Cherchée ici, une fois les middlewares passés : l'organisation courante
     * est posée et le cloisonnement limite la recherche à ses images.
     */
    public function image(): OrganizationImage
    {
        return $this->image ??= OrganizationImage::query()->findOrFail((int) $this->route('image'));
    }

    /**
     * @return list<callable>
     */
    public function after(FindOrganizationImageUsage $findOrganizationImageUsage): array
    {
        return [function (Validator $validator) use ($findOrganizationImageUsage): void {
            $usages = $findOrganizationImageUsage->handle($this->image());

            if ($usages !== []) {
                $validator->errors()->add('image', 'Cette image est encore utilisée ('.implode(', ', $usages).'). Retirez-la de ces emplacements avant de la supprimer.');
            }
        }];
    }
}
