<?php

declare(strict_types=1);

namespace App\Domain\Form\Support;

use App\Domain\Form\Data\CompanionData;
use App\Domain\Form\Data\FormVisibilityContext;
use App\Domain\Form\Models\FormVersion;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Règles des questions posées à chaque accompagnant : le même moteur que
 * pour le titulaire (BuildFormValidationRules), limité aux questions « par
 * personne ». Les réponses d'un accompagnant vivent sous
 * _companions.{rang}.answers, à part de son nom : une question dont la clé
 * serait « first_name » ne peut ainsi jamais se confondre avec lui.
 */
final class ValidateCompanions
{
    public function __construct(
        private readonly BuildFormValidationRules $buildFormValidationRules,
    ) {}

    /**
     * @param  array<string, mixed>  $holderAnswers
     * @param  array<int, array<string, mixed>>  $companionsAnswers  réponses de chaque accompagnant, par rang
     * @return array<string, list<mixed>>
     */
    public function rules(FormVersion $version, array $holderAnswers, array $companionsAnswers, ?FormVisibilityContext $context): array
    {
        $rules = [];

        foreach ($companionsAnswers as $index => $companionAnswers) {
            $answers = AskScope::answersFor($version, $holderAnswers, $companionAnswers);

            foreach ($this->buildFormValidationRules->handle($version, $answers, $context, perPersonOnly: true) as $key => $fieldRules) {
                $rules[self::answerKey($index, $key)] = $fieldRules;
            }
        }

        return $rules;
    }

    public static function answerKey(int $index, string $fieldKey): string
    {
        return CompanionData::INPUT_KEY.".{$index}.answers.{$fieldKey}";
    }

    /**
     * @param  array<string, mixed>  $holderAnswers
     * @param  list<CompanionData>  $companions
     *
     * @throws ValidationException
     */
    public function handle(FormVersion $version, array $holderAnswers, array $companions, ?FormVisibilityContext $context): void
    {
        $companionsAnswers = [];

        foreach ($companions as $index => $companion) {
            if (trim($companion->firstName) === '') {
                throw ValidationException::withMessages([
                    CompanionData::INPUT_KEY.".{$index}.first_name" => 'Indiquez le prénom de chaque accompagnant.',
                ]);
            }

            $companionsAnswers[$index] = $companion->answers;
        }

        $rules = $this->rules($version, $holderAnswers, $companionsAnswers, $context);

        if ($rules === []) {
            return;
        }

        $data = [];

        foreach ($companionsAnswers as $index => $companionAnswers) {
            $data[$index] = ['answers' => AskScope::answersFor($version, $holderAnswers, $companionAnswers)];
        }

        Validator::make([CompanionData::INPUT_KEY => $data], $rules)->validate();
    }
}
