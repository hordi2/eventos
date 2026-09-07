<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

/**
 * Registre des traitements (T-075, RGPD §10.2 P-09 : « registre des
 * traitements généré »). Le contenu structurel (finalités, bases légales,
 * destinataires) reflète les traitements réellement effectués par le
 * produit ; la durée de conservation des contacts est injectée depuis la
 * configuration en vigueur (config/gdpr.php) plutôt qu'écrite en dur,
 * pour ne jamais afficher une durée périmée si elle est reconfigurée.
 */
final class GetProcessingRegister
{
    /**
     * @return list<array{finalite: string, donnees: string, base_legale: string, destinataires: string, conservation: string}>
     */
    public function handle(): array
    {
        $contactRetentionMonths = (int) config('gdpr.contact_retention_months');

        return [
            [
                'finalite' => 'Inscription et gestion des invités (RSVP)',
                'donnees' => 'Identité, coordonnées, réponses au formulaire d\'inscription',
                'base_legale' => 'Exécution du contrat / intérêt légitime de l\'organisateur',
                'destinataires' => 'Organisateur de l\'événement, hébergeur',
                'conservation' => "{$contactRetentionMonths} mois après la dernière activité du contact, puis anonymisation automatique",
            ],
            [
                'finalite' => 'Billetterie et paiement',
                'donnees' => 'Identité de l\'acheteur, montant, statut de la commande (les données de carte bancaire ne transitent jamais par la plateforme)',
                'base_legale' => 'Exécution du contrat',
                'destinataires' => 'Organisateur, prestataire de paiement (Stripe, Flutterwave)',
                'conservation' => 'Durée légale de conservation comptable applicable à l\'organisation',
            ],
            [
                'finalite' => 'Communication (e-mail, WhatsApp)',
                'donnees' => 'Coordonnées, préférences de consentement par canal, historique d\'envoi',
                'base_legale' => 'Consentement de la personne, révocable à tout moment',
                'destinataires' => 'Organisateur, prestataires d\'envoi (Postmark, Twilio)',
                'conservation' => 'Jusqu\'au retrait du consentement ou anonymisation du contact',
            ],
            [
                'finalite' => 'Contrôle d\'accès et présence (check-in)',
                'donnees' => 'Identité, horodatage de passage, table assignée',
                'base_legale' => 'Exécution du contrat',
                'destinataires' => 'Organisateur, équipe d\'accueil',
                'conservation' => 'Alignée sur la conservation de l\'inscription correspondante',
            ],
            [
                'finalite' => 'Journal d\'audit et sécurité',
                'donnees' => 'Action effectuée, auteur, horodatage, adresse IP (les valeurs identifiantes des champs modifiés ne sont pas journalisées en clair)',
                'base_legale' => 'Intérêt légitime (sécurité, preuve)',
                'destinataires' => 'Organisateur (rôles Owner/Admin)',
                'conservation' => '24 mois minimum, journal techniquement immuable',
            ],
        ];
    }
}
