<?php

declare(strict_types=1);

namespace App\Domain\Form\Support;

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
     * @return array<string, list<mixed>>
     */
    public function handle(FormVersion $version, array $answers): array
    {
        $visibility = $this->evaluateFormVisibility->handle($version, $answers);
        $rules = [];

        foreach ($version->fields as $field) {
            $state = $visibility[$field->key];

            if (! $state['visible']) {
                $rules[$field->key] = ['prohibited'];

                continue;
            }

            $fieldRules = $this->fieldValidationRules->forField($field);
            $fieldRules[$field->key][0] = $state['required'] ? 'required' : $fieldRules[$field->key][0];

            $rules = [...$rules, ...$fieldRules];
        }

        return $rules;
    }
}
