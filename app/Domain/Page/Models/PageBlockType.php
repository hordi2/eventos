<?php

declare(strict_types=1);

namespace App\Domain\Page\Models;

/**
 * Blocs de la page publique d'un événement (lot 2).
 */
enum PageBlockType: string
{
    case Text = 'text';
    case Image = 'image';
    case Program = 'program';
    case Faq = 'faq';
    case Venue = 'venue';
    case Video = 'video';
    case Countdown = 'countdown';
    case Speakers = 'speakers';
    case Sessions = 'sessions';
    case GuestBook = 'guest_book';
    case SaveTheDate = 'save_the_date';
    case Details = 'details';
    case WelcomeMessage = 'welcome_message';
    case EntryQr = 'entry_qr';
    case Gallery = 'gallery';
    case Rsvp = 'rsvp';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Texte',
            self::Image => 'Image',
            self::Program => 'Programme',
            self::Faq => 'Questions fréquentes',
            self::Venue => 'Lieu et plan',
            self::Video => 'Vidéo',
            self::Countdown => 'Compte à rebours',
            self::Speakers => 'Intervenants',
            self::Sessions => 'Programme des sessions',
            self::GuestBook => "Livre d'or",
            self::SaveTheDate => 'Save the date',
            self::Details => 'Informations en cartes',
            self::WelcomeMessage => "Mot d'accueil",
            self::EntryQr => 'Votre entrée (code QR)',
            self::Gallery => 'Galerie photo',
            self::Rsvp => 'Confirmez votre présence',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Text => 'Un titre et un texte libre.',
            self::Image => 'Une photo, pleine largeur.',
            self::Program => 'Le déroulé, heure par heure.',
            self::Faq => 'Des questions dépliables.',
            self::Venue => "L'adresse de l'événement et son plan.",
            self::Video => 'Une vidéo YouTube ou Vimeo, chargée au clic.',
            self::Countdown => "Le temps qu'il reste avant le début.",
            self::Speakers => 'Les fiches des intervenants, avec photo et biographie.',
            self::Sessions => 'Vos sessions, avec leur horaire, leur salle et leurs intervenants.',
            self::GuestBook => 'Vos invités laissent un mot, que tout le monde peut lire.',
            self::SaveTheDate => 'Le calendrier du mois, la date en grand et votre mot de convocation.',
            self::Details => 'Thème, tenue, tapis rouge, cadeaux : une carte par information.',
            self::WelcomeMessage => 'Une vidéo ou un enregistrement audio, joué au clic.',
            self::EntryQr => "Le code d'entrée de l'invité, à présenter à l'accueil.",
            self::Gallery => 'Vos photos, en grille, agrandies au clic.',
            self::Rsvp => 'Les trois réponses possibles, en gros boutons.',
        };
    }

    /**
     * @return list<array{value: string, label: string, hint: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label(), 'hint' => $type->hint()],
            self::cases(),
        );
    }
}
