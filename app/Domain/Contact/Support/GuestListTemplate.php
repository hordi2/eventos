<?php

declare(strict_types=1);

namespace App\Domain\Contact\Support;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Modèle Excel de la liste d'invités : une feuille « Invités » à remplir,
 * dont les en-têtes sont reconnus tels quels au mappage (GuessColumnMapping),
 * et une feuille « Mode d'emploi ». Les exemples vivent dans cette seconde
 * feuille : importer le modèle tel quel n'ajoute personne par erreur.
 */
final class GuestListTemplate
{
    /**
     * @var list<array{0: string, 1: string, 2: string, 3: string}> colonne, exigence, rôle, exemple
     */
    public const COLUMNS = [
        ['Prénom', 'Nom complet et/ou e-mail', "Avec le nom, identifie l'invité. Un nom complet peut aussi tenir dans cette seule colonne.", 'Grace'],
        ['Nom', 'Nom complet et/ou e-mail', 'Nom de famille.', 'Mbuyi'],
        ['E-mail', 'Nom complet et/ou e-mail', 'Pour envoyer l\'invitation par e-mail et retrouver l\'invité quand il répond.', 'grace.mbuyi@exemple.cd'],
        ['Téléphone', 'Facultatif', 'Pour WhatsApp et les SMS, au format international.', '+243 81 234 5678'],
        ['Groupe', 'Facultatif', 'Les invités qui ont la même valeur répondent ensemble (un couple, une famille). Laissez vide pour un invité seul.', '1'],
        ['Accompagnants autorisés', 'Facultatif', 'Personnes que l\'invité peut amener en plus de lui : vide ou 0 pour aucune, un nombre jusqu\'à 20, ou « illimité ».', '2'],
        ['Tags', 'Facultatif', 'Pour trier la liste ou inviter à une session. Séparez plusieurs tags par des virgules.', 'VIP, Dîner'],
        ['E-mail en copie', 'Facultatif', 'Adresse mise en copie des envois faits à cet invité, par exemple une assistante.', 'assistante@exemple.cd'],
    ];

    /**
     * @var list<list<string>>
     */
    private const EXAMPLE_GUESTS = [
        ['Grace', 'Mbuyi', 'grace.mbuyi@exemple.cd', '+243 81 234 5678', '1', '2', 'VIP, Dîner', ''],
        ['Patrick', 'Mbuyi', 'patrick.mbuyi@exemple.cd', '', '1', '', 'Dîner', ''],
        ['Awa', 'Diallo', 'awa.diallo@exemple.sn', '+221 77 123 45 67', '', 'illimité', '', 'secretariat@exemple.sn'],
        ['Jean-Marc', 'Ekra', '', '+225 07 00 00 00 00', '2', '1', '', ''],
        ['Aïcha', 'Ekra', '', '', '2', '', '', ''],
    ];

    /**
     * @var list<string>
     */
    private const TIPS = [
        'Gardez les en-têtes de la première ligne : Itaza les reconnaît et vous proposera de vérifier la correspondance des colonnes.',
        "Chaque invité a besoin d'un nom complet (prénom et nom) et/ou d'une adresse e-mail.",
        "L'e-mail n'est indispensable que pour inviter par e-mail ou permettre à l'invité de se retrouver par son adresse.",
        'Un invité déjà présent dans vos contacts est reconnu : sa fiche n\'est pas dupliquée.',
        'Réimporter le fichier met les invités à jour au lieu de les ajouter une seconde fois.',
        "N'importez que des personnes qui ont accepté de recevoir vos invitations.",
    ];

    /**
     * Chemin d'un fichier temporaire ; l'appelant l'efface une fois envoyé.
     */
    public function write(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'modele-invites').'.xlsx';
        $header = new Style(fontBold: true, backgroundColor: Color::rgb(243, 239, 233));
        $title = new Style(fontBold: true, fontSize: 14);
        $wrapped = new Style(shouldWrapText: true);

        $writer = new Writer;
        $writer->openToFile($path);

        $guests = $writer->getCurrentSheet()->setName('Invités');
        $guests->setColumnWidth(24, 1, 2, 5, 6, 7);
        $guests->setColumnWidth(32, 3, 4, 8);
        $writer->addRow(Row::fromValuesWithStyle(array_column(self::COLUMNS, 0), $header));

        $guide = $writer->addNewSheetAndMakeItCurrent()->setName("Mode d'emploi");
        $guide->setColumnWidth(26, 1, 2);
        $guide->setColumnWidth(70, 3);
        $guide->setColumnWidth(26, 4, 5, 6, 7, 8);

        $writer->addRow(Row::fromValuesWithStyle(['Remplir la feuille « Invités »'], $title));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValuesWithStyle(['Colonne', 'Obligatoire ?', 'À quoi elle sert', 'Exemple'], $header));

        foreach (self::COLUMNS as $column) {
            $writer->addRow(Row::fromValuesWithStyles($column, [2 => $wrapped]));
        }

        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValuesWithStyle(['Conseils'], $title));

        foreach (self::TIPS as $tip) {
            $writer->addRow(Row::fromValues(['', '', $tip]));
        }

        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValuesWithStyle(['Exemple de liste remplie'], $title));
        $writer->addRow(Row::fromValuesWithStyle(array_column(self::COLUMNS, 0), $header));

        foreach (self::EXAMPLE_GUESTS as $guest) {
            $writer->addRow(Row::fromValues($guest));
        }

        $writer->close();

        return $path;
    }
}
