<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Messaging\Models\WhatsappTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class UpdateWhatsappTemplate
{
    /**
     * @param  list<string>  $variableMapping
     */
    public function handle(
        WhatsappTemplate $template,
        User $updater,
        string $name,
        string $providerTemplateSid,
        string $language,
        ?string $category,
        array $variableMapping,
    ): WhatsappTemplate {
        Gate::forUser($updater)->authorize('update', $template);

        $template->update([
            'name' => $name,
            'provider_template_sid' => $providerTemplateSid,
            'language' => $language,
            'category' => $category,
            'variable_mapping' => $variableMapping,
        ]);

        return $template;
    }
}
