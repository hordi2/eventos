<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\InvalidConditionalRuleException;
use App\Domain\Form\Models\ConditionalRule;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\RuleAction;
use App\Domain\Form\Support\DetectCircularRuleDependency;

/**
 * Crée les ConditionalRule d'une FormVersion à partir d'un tableau de
 * définitions — utilisé par CreateForm, UpdateFormDraft et ReviseForm,
 * jamais appelé directement en dehors de ces actions (même principe que
 * WriteFormFields pour les champs).
 */
final class WriteConditionalRules
{
    public function __construct(
        private readonly DetectCircularRuleDependency $detectCircularRuleDependency,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $rules
     */
    public function handle(FormVersion $version, array $rules): void
    {
        if ($rules === []) {
            return;
        }

        $fieldsByKey = $version->fields()->get()->keyBy('key');
        $conditionGroupsByTargetKey = [];

        foreach ($rules as $ruleData) {
            $targetKey = $ruleData['target_field_key'];

            if (! $fieldsByKey->has($targetKey)) {
                throw InvalidConditionalRuleException::unknownField($targetKey);
            }

            if (isset($conditionGroupsByTargetKey[$targetKey])) {
                throw InvalidConditionalRuleException::duplicateTarget($targetKey);
            }

            $conditionGroupsByTargetKey[$targetKey] = $ruleData['condition_group'];
        }

        if ($this->detectCircularRuleDependency->hasCycle($conditionGroupsByTargetKey)) {
            throw InvalidConditionalRuleException::circularDependency();
        }

        foreach ($rules as $ruleData) {
            $targetField = $fieldsByKey->get($ruleData['target_field_key']);

            ConditionalRule::query()->create([
                'organization_id' => $version->organization_id,
                'form_version_id' => $version->id,
                'target_field_id' => $targetField->id,
                'action' => RuleAction::from($ruleData['action']),
                'condition_group' => $ruleData['condition_group'],
            ]);
        }
    }
}
