<?php

declare(strict_types=1);

namespace App\Domain\Form\Support;

use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\FormField;
use App\Domain\Form\Models\FormVersion;

/**
 * Réglage « Poser la question » d'un bloc (config.ask_scope) : une fois pour
 * toute l'inscription, ou à chaque personne — titulaire et accompagnants
 * (T-032).
 */
final class AskScope
{
    public const ONCE = 'once';

    public const EACH_ATTENDEE = 'each_attendee';

    /**
     * Le choix des événements secondaires, le don et le fichier joint valent
     * pour tout le groupe : ils ne se posent jamais à chaque personne.
     */
    public static function isPerPerson(FormField $field): bool
    {
        return ! in_array($field->type, [FieldType::SubEvents, FieldType::Donation, FieldType::DonorInfo, FieldType::FileUpload], true)
            && (($field->config ?? [])['ask_scope'] ?? self::ONCE) === self::EACH_ATTENDEE;
    }

    /**
     * Réponses vues par la logique conditionnelle pour un accompagnant :
     * celles du titulaire aux questions communes, les siennes aux questions
     * posées à chaque personne — jamais celles du titulaire à sa place.
     *
     * @param  array<string, mixed>  $holderAnswers
     * @param  array<string, mixed>  $companionAnswers
     * @return array<string, mixed>
     */
    public static function answersFor(FormVersion $version, array $holderAnswers, array $companionAnswers): array
    {
        $answers = $holderAnswers;

        foreach ($version->fields as $field) {
            if (! self::isPerPerson($field)) {
                continue;
            }

            unset($answers[$field->key]);

            if (array_key_exists($field->key, $companionAnswers)) {
                $answers[$field->key] = $companionAnswers[$field->key];
            }
        }

        return $answers;
    }
}
