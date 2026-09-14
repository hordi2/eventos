<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

/**
 * Les 12 types MVP du CDC (M2.1), plus les 6 types ajoutés avec le
 * constructeur à blocs (liste déroulante, date et heure, lien web, profil
 * social, quantité, adresse postale). La validation, le rendu et l'export
 * propres à chaque type vivent dans FieldValidationRules,
 * NormalizeFieldAnswer, FormatFieldAnswerForExport et la vue _field.
 */
enum FieldType: string
{
    case ShortText = 'short_text';
    case LongText = 'long_text';
    case Number = 'number';
    case Email = 'email';
    case Phone = 'phone';
    case Date = 'date';
    case SingleChoice = 'single_choice';
    case MultipleChoice = 'multiple_choice';
    case YesNo = 'yes_no';
    case Consent = 'consent';
    case MealChoice = 'meal_choice';
    case InformationalText = 'informational_text';
    case Dropdown = 'dropdown';
    case DateTime = 'date_time';
    case Url = 'url';
    case SocialProfile = 'social_profile';
    case Quantity = 'quantity';
    case PostalAddress = 'postal_address';

    public function supportsOptions(): bool
    {
        return match ($this) {
            self::SingleChoice, self::MultipleChoice, self::MealChoice, self::Dropdown => true,
            default => false,
        };
    }

    /**
     * Questions avancées : libres d'essai au plan Gratuit, mais publier un
     * formulaire qui en contient demande un plan payant (PublishFormVersion,
     * décision produit). Les 12 types d'origine restent gratuits : aucun
     * formulaire existant ne devient impubliable.
     */
    public function isPremium(): bool
    {
        return in_array($this, [self::DateTime, self::Url, self::SocialProfile, self::Quantity, self::PostalAddress], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::ShortText => 'Texte sur une ligne',
            self::LongText => 'Texte sur plusieurs lignes',
            self::Number => 'Nombre',
            self::Email => 'E-mail',
            self::Phone => 'Numéro de téléphone',
            self::Date => 'Date',
            self::SingleChoice => 'Choix unique',
            self::MultipleChoice => 'Choix multiples',
            self::YesNo => 'Oui / Non',
            self::Consent => 'Conditions à accepter',
            self::MealChoice => 'Menu / repas',
            self::InformationalText => 'Texte informatif',
            self::Dropdown => 'Liste déroulante',
            self::DateTime => 'Date et heure',
            self::Url => 'Lien web',
            self::SocialProfile => 'Profil de réseau social',
            self::Quantity => 'Quantité',
            self::PostalAddress => 'Adresse postale',
        };
    }
}
