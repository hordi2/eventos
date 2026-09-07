<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

/**
 * Un même palier gratuit, plus deux familles de paliers payants distinctes
 * (personnel / professionnel, demande utilisateur) — chacune avec ses
 * propres tarifs et quotas Stripe, présentées sous des onglets séparés sur
 * la page « Mise à niveau » (voir Billing/Show.tsx). « Sur mesure »
 * (Entreprise) n'a volontairement pas d'équivalent ici : ce palier se
 * négocie avec l'équipe plutôt que de s'acheter en un clic, comme chez la
 * plupart des éditeurs SaaS.
 */
enum PlanTier: string
{
    case Free = 'free';
    case PersonalEssential = 'personal_essential';
    case PersonalComfort = 'personal_comfort';
    case ProfessionalPlus = 'professional_plus';
    case ProfessionalCareer = 'professional_career';
    case ProfessionalPro = 'professional_pro';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Gratuit',
            self::PersonalEssential => 'Essentiel',
            self::PersonalComfort => 'Confort',
            self::ProfessionalPlus => 'En plus',
            self::ProfessionalCareer => 'Carrière professionnelle',
            self::ProfessionalPro => 'Pro',
        };
    }
}
