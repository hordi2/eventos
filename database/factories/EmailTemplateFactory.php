<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Messaging\Models\EmailTemplate;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailTemplate>
 */
final class EmailTemplateFactory extends Factory
{
    protected $model = EmailTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'name' => fake()->sentence(3),
            'subject' => fake()->sentence(4),
            'blocks' => [
                ['type' => 'heading', 'text' => 'Bonjour {{first_name}}'],
                ['type' => 'text', 'html' => '<p>Merci de confirmer votre présence.</p>'],
                ['type' => 'button', 'text' => 'Répondre', 'url' => '{{rsvp_link}}'],
            ],
        ];
    }
}
