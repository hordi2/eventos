<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Messaging\Models\WhatsappTemplate;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class CreateWhatsappTemplate
{
    /**
     * @param  list<string>  $variableMapping
     */
    public function handle(
        Organization $organization,
        User $creator,
        string $name,
        string $providerTemplateSid,
        string $language,
        ?string $category,
        array $variableMapping,
    ): WhatsappTemplate {
        Gate::forUser($creator)->authorize('create', [WhatsappTemplate::class, $organization]);

        return WhatsappTemplate::query()->create([
            'organization_id' => $organization->id,
            'created_by' => $creator->id,
            'name' => $name,
            'provider_template_sid' => $providerTemplateSid,
            'language' => $language,
            'category' => $category,
            'variable_mapping' => $variableMapping,
        ]);
    }
}
