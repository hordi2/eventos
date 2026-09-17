<?php

declare(strict_types=1);

namespace App\Domain\Form\Support;

use App\Domain\Form\Data\FormVisibilityContext;
use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\FormVersion;

/**
 * Combine EvaluateFormVisibility et FieldValidationRules pour produire les
 * règles Laravel d'une soumission complète : un champ masqué par la
 * logique conditionnelle est explicitement interdit ("prohibited"), jamais
 * simplement facultatif — c'est ce qui garantit qu'il n'est « jamais validé
 * ni enregistré » (critère du ticket T-022), puisqu'une valeur soumise pour
 * un champ prohibited fait échouer la validation avant tout enregistrement.
 */
final class BuildFormValidationRules
{
    public function __construct(
        private readonly EvaluateFormVisibility $evaluateFormVisibility,
        private readonly FieldValidationRules $fieldValidationRules,
    ) {}

    /**
     * @param  array<string, mixed>  $answers
     * @param  bool  $perPersonOnly  règles d'un accompagnant : seulement les questions posées à chaque personne (T-032)
     * @param  bool  $excludeLocked  modification d'une inscription : les réponses figées (don, T-056) ne se revalident pas
     * @return array<string, list<mixed>>
     */
    public function handle(FormVersion $version, array $answers, ?FormVisibilityContext $context = null, bool $perPersonOnly = false, bool $excludeLocked = false): array
    {
        $visibility = $this->evaluateFormVisibility->handle($version, $answers, $context);
        // Les informations du donateur ne s'exigent que d'un invité qui donne.
        $donationGiven = DonationAnswer::isGiven($version, $answers, $visibility);
        $rules = [];

        foreach ($version->fields as $field) {
            if (($perPersonOnly && ! AskScope::isPerPerson($field)) || ($excludeLocked && $field->type->isLockedAfterSubmission())) {
                continue;
            }

            $state = $visibility[$field->key];

            if (! $state['visible']) {
                $rules[$field->key] = ['prohibited'];

                continue;
            }

            $required = ($state['required'] || $field->is_required)
                && ($field->type !== FieldType::DonorInfo || $donationGiven);

            $rules = [...$rules, ...$this->fieldValidationRules->forField($field, $required)];
        }

        return $rules;
    }
}
