<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Messaging\Models\WhatsappTemplate;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappTemplate>
 */
final class WhatsappTemplateFactory extends Factory
{
    protected $model = WhatsappTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'name' => fake()->sentence(3),
            'provider_template_sid' => 'HX'.fake()->regexify('[0-9a-f]{32}'),
            'language' => 'fr',
            'category' => 'utility',
            'variable_mapping' => ['first_name', 'event_date'],
        ];
    }
}
