<?php

declare(strict_types=1);

namespace App\Support\Registration;

use App\Domain\Form\Actions\FormatFieldAnswerForExport;
use App\Domain\Form\Models\RegistrationAnswer;
use Illuminate\Support\Collection;

/**
 * Réponses d'une inscription prêtes à lire : une valeur par clé de question,
 * la réponse d'un accompagnant précédée de son prénom. Partagée par l'écran
 * des réponses, les rapports et l'export, pour que l'organisateur lise
 * partout la même chose.
 */
final class CollectRegistrationAnswers
{
    public function __construct(
        private readonly FormatFieldAnswerForExport $formatAnswer,
    ) {}

    /**
     * @param  Collection<int, RegistrationAnswer>  $answers  réponses d'une inscription, titulaire et accompagnants
     * @return array<string, string>
     */
    public function handle(Collection $answers, string $separator = "\n"): array
    {
        $rows = [];

        foreach ($answers as $answer) {
            $field = $answer->formField;

            if ($field === null) {
                continue;
            }

            $value = trim($this->formatAnswer->handle($field, $answer->value));

            if ($value === '') {
                continue;
            }

            $companion = $answer->attendee;
            $line = $companion !== null ? trim("{$companion->first_name} : {$value}") : $value;
            $rows[$field->key] = isset($rows[$field->key]) ? $rows[$field->key].$separator.$line : $line;
        }

        return $rows;
    }
}
