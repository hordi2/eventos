<?php

declare(strict_types=1);

namespace App\Domain\Event\Models;

/**
 * Les 14 étapes de la liste de contrôle d'un événement. Chacune renvoie vers
 * une page Itaza qui existe déjà (clé de lien de BuildEventNavigation) ;
 * aucune ne promet une fonctionnalité absente.
 */
enum EventChecklistStep: string
{
    case Website = 'site_web';
    case Form = 'formulaire';
    case GuestList = 'liste_invites';
    case ConfirmationEmails = 'emails_confirmation';
    case Preview = 'apercu';
    case Publish = 'publication';
    case Invitations = 'invitations';
    case ShareLink = 'lien_partage';
    case Responses = 'suivi_reponses';
    case Seating = 'plan_de_table';
    case CheckIn = 'check_in';
    case Export = 'export';
    case Attendance = 'frequentation';
    case ThankYou = 'remerciements';

    public function tab(): EventChecklistTab
    {
        return match ($this) {
            self::Website, self::Form, self::GuestList, self::ConfirmationEmails => EventChecklistTab::Personalize,
            self::Preview, self::Publish, self::Invitations, self::ShareLink => EventChecklistTab::Launch,
            self::Responses, self::Seating, self::CheckIn => EventChecklistTab::Organize,
            self::Export, self::Attendance, self::ThankYou => EventChecklistTab::FollowUp,
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::Website => "Site web de l'événement",
            self::Form => "Formulaire d'inscription",
            self::GuestList => 'Liste des invités',
            self::ConfirmationEmails => 'E-mails de confirmation et rappels',
            self::Preview => "Aperçu de l'événement",
            self::Publish => 'Publication',
            self::Invitations => 'Invitations par e-mail',
            self::ShareLink => "Lien de l'événement",
            self::Responses => 'Suivi des réponses',
            self::Seating => 'Plan de table',
            self::CheckIn => 'Check-in',
            self::Export => 'Export des données',
            self::Attendance => 'Bilan de fréquentation',
            self::ThankYou => 'E-mails de remerciement',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Website => 'Présentez votre événement sur une page publique : bannière, programme et questions fréquentes.',
            self::Form => 'Choisissez les informations demandées à chaque invité, puis publiez le formulaire.',
            self::GuestList => 'Retrouvez vos inscrits par segment et importez vos contacts pour les inviter.',
            self::ConfirmationEmails => "Programmez la confirmation envoyée après l'inscription et les rappels avant le jour J.",
            self::Preview => "Parcourez la page et le formulaire comme un invité avant d'ouvrir les inscriptions.",
            self::Publish => "Mettez l'événement en ligne : son lien public ouvre les inscriptions.",
            self::Invitations => "Envoyez l'invitation à vos contacts, puis relancez ceux qui n'ont pas répondu.",
            self::ShareLink => 'Copiez le lien public pour le diffuser sur WhatsApp, les réseaux sociaux ou par SMS.',
            self::Responses => "Suivez en direct les confirmations, les refus et la liste d'attente.",
            self::Seating => 'Créez vos tables et placez vos invités par glisser-déposer.',
            self::CheckIn => 'Accueillez les invités le jour J en scannant leur QR code, même sans connexion.',
            self::Export => 'Téléchargez la liste des inscrits et des présences pour vos archives.',
            self::Attendance => 'Vérifiez qui est venu et à quelle heure.',
            self::ThankYou => "Remerciez les invités présents après l'événement.",
        };
    }

    public function isOptional(): bool
    {
        return in_array($this, [self::Website, self::GuestList, self::Invitations, self::Seating], true);
    }

    /**
     * La publication découle du statut de l'événement : la cocher à la main
     * afficherait « Complet » pour un événement encore hors ligne.
     */
    public function canBeMarkedManually(): bool
    {
        return $this !== self::Publish;
    }

    /**
     * Clé du lien correspondant dans BuildEventNavigation.
     */
    public function linkKey(): string
    {
        return match ($this) {
            self::Website => 'website',
            self::Form => 'form',
            self::GuestList => 'guests',
            self::ConfirmationEmails, self::Invitations, self::ThankYou => 'communications',
            self::Preview => 'preview',
            self::Publish => 'publish',
            self::ShareLink => 'share',
            self::Responses => 'responses',
            self::Seating => 'seating',
            self::CheckIn, self::Attendance => 'checkIn',
            self::Export => 'exports',
        };
    }
}
