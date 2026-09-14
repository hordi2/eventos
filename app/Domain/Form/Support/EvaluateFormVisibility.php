<?php

declare(strict_types=1);

namespace App\Domain\Form\Support;

use App\Domain\Form\Data\FormVisibilityContext;
use App\Domain\Form\Models\FormField;
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
     * @param  FormVisibilityContext|null  $context  ce que l'on sait de l'invité ; null : public du champ non appliqué
     * @return array<string, array{visible: bool, required: bool}>
     */
    public function handle(FormVersion $version, array $answers, ?FormVisibilityContext $context = null): array
    {
        $state = [];
        $inAudience = [];

        foreach ($version->fields as $field) {
            $state[$field->key] = ['visible' => true, 'required' => $field->is_required];
            $inAudience[$field->key] = $context === null || $this->matchesAudience($field, $context);
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

        // Le public d'un champ s'ajoute à la logique conditionnelle sans
        // jamais être contredit par elle : hors de son public, un champ reste
        // masqué quelle que soit la règle.
        foreach ($inAudience as $key => $allowed) {
            if (! $allowed) {
                $state[$key]['visible'] = false;
            }
        }

        return $state;
    }

    /**
     * Réglages « Demander si » (config.show_if) et « Seulement pour les
     * invités portant le tag… » (config.tag_ids) du constructeur. Sans
     * show_if, un champ s'adresse aux invités qui viennent : c'est ce qui
     * évite de poser le choix du menu à quelqu'un qui décline.
     */
    private function matchesAudience(FormField $field, FormVisibilityContext $context): bool
    {
        $config = $field->config ?? [];
        $showIf = $config['show_if'] ?? 'attending';

        if ($showIf === 'attending' && ! $context->attending) {
            return false;
        }

        if ($showIf === 'not_attending' && $context->attending) {
            return false;
        }

        $tagIds = array_map(intval(...), (array) ($config['tag_ids'] ?? []));

        if ($tagIds === [] || $context->tagIds === null) {
            return true;
        }

        return array_intersect($tagIds, $context->tagIds) !== [];
    }
}
