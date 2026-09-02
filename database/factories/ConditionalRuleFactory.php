<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Form\Models\ConditionalRule;
use App\Domain\Form\Models\FormField;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\RuleAction;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConditionalRule>
 */
final class ConditionalRuleFactory extends Factory
{
    protected $model = ConditionalRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'form_version_id' => FormVersion::factory(),
            'target_field_id' => FormField::factory(),
            'action' => RuleAction::Show,
            'condition_group' => [
                'combinator' => 'and',
                'conditions' => [
                    ['field_key' => 'trigger', 'operator' => 'is', 'value' => 'oui'],
                ],
            ],
        ];
    }
}
