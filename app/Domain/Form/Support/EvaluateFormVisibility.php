<?php

declare(strict_types=1);

namespace App\Domain\Form\Support;

use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\RuleAction;

/**
 * Calcule, pour une version de formulaire et un jeu de réponses donné,
 * quels champs sont visibles et lesquels sont rendus obligatoires par une
 * règle — sert à la fois à la validation serveur d'une soumission (T-030)
 * et au mode « simuler une réponse » de l'éditeur (T-023, dont le seul
 * travail restant sera un bouton qui appelle cette même méthode).
 *
 * Chaque champ n'a au plus une règle (contrainte d'unicité en base), donc
 * aucune histoire de précédence à arbitrer entre plusieurs règles.
 */
final class EvaluateFormVisibility
{
    public function __construct(
        private readonly ConditionGroupEvaluator $conditionGroupEvaluator,
    ) {}

    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, array{visible: bool, required: bool}>
     */
    public function handle(FormVersion $version, array $answers): array
    {
        $state = [];

        foreach ($version->fields as $field) {
            $state[$field->key] = ['visible' => true, 'required' => $field->is_required];
        }

        foreach ($version->conditionalRules as $rule) {
            $key = $rule->targetField->key;
            $matched = $this->conditionGroupEvaluator->evaluate($rule->condition_group, $answers);

            match ($rule->action) {
                RuleAction::Show => $state[$key]['visible'] = $matched,
                RuleAction::Hide => $state[$key]['visible'] = ! $matched,
                RuleAction::Require => $state[$key]['required'] = $matched,
            };
        }

        return $state;
    }
}
