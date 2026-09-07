import { Head } from '@inertiajs/react';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

const startSteps = [
    {
        title: 'Personnalisez votre charte graphique',
        body: "Ajoutez le logo et la couleur principale de votre organisation (Organisation → Charte graphique). Ils s'appliquent automatiquement à la page événement publique et à l'en-tête de vos e-mails.",
    },
    {
        title: 'Créez votre premier événement',
        body: "Depuis Événements → Créer un événement, renseignez le titre, les dates, le lieu et le fuseau horaire. Un événement reste en brouillon tant que vous ne le publiez pas.",
    },
    {
        title: "Construisez le formulaire d'inscription",
        body: "Ajoutez les champs nécessaires (texte, choix, foyer, don libre...) et, si besoin, une logique conditionnelle pour n'afficher un champ que selon une réponse précédente.",
    },
    {
        title: 'Configurez la billetterie si votre événement est payant',
        body: 'Créez vos types de billets et leurs paliers de prix. Les paiements par carte et par Mobile Money sont pris en charge nativement, sans intégration supplémentaire.',
    },
    {
        title: 'Publiez et invitez vos contacts',
        body: "Publiez l'événement, puis envoyez vos invitations par e-mail et par WhatsApp — les deux canaux ont le même niveau de priorité sur Itaza.",
    },
    {
        title: 'Le jour J, faites le check-in',
        body: "Utilisez le check-in web (Événements → Check-in) ou l'application mobile dédiée, qui fonctionne entièrement hors connexion et se synchronise dès que le réseau revient.",
    },
];

const tutorials = [
    {
        title: 'Créer et publier un événement',
        body: 'Un événement peut inclure des sous-événements (ateliers, sessions) avec leur propre inscription et leur propre capacité. Dupliquez un événement existant pour repartir de sa configuration (formulaire, billetterie, charte) sans tout recréer.',
    },
    {
        title: 'Construire un formulaire d’inscription',
        body: "Le moteur de formulaire couvre les types de champs courants et la logique conditionnelle. Attention : modifier un formulaire déjà publié ne change jamais l'interprétation des réponses déjà reçues — chaque réponse reste liée à la version du formulaire au moment de sa soumission.",
    },
    {
        title: 'Gérer les contacts, foyers et tags',
        body: 'La section Contacts centralise tous les invités déjà connus, quel que soit l’événement. Regroupez-les par foyer (une famille qui répond ensemble) ou par tag pour cibler vos envois et vos exports.',
    },
    {
        title: 'Envoyer des invitations et automatiser les relances',
        body: "Créez des modèles d'e-mail et de WhatsApp (Communications), puis programmez des séquences de relance. Un modèle WhatsApp doit d'abord être approuvé par Meta via votre compte WhatsApp Business — comptez plusieurs jours pour cette approbation la première fois.",
    },
    {
        title: 'Vendre des billets et encaisser',
        body: "Les commandes de billetterie acceptent la carte bancaire et le Mobile Money (Orange Money, MTN Money, Airtel Money selon les pays pris en charge par l'agrégateur). Un paiement Mobile Money reste « en attente » le temps de la confirmation asynchrone de l'opérateur — c'est normal, pas une erreur.",
    },
    {
        title: 'Faire le check-in le jour de l’événement',
        body: "Le check-in fonctionne aussi bien depuis un navigateur que depuis l'application mobile Itaza Check-in. L'application mobile est conçue pour l'absence totale de réseau sur place : elle télécharge la liste des invités à l'avance, scanne les QR codes en local, et synchronise les enregistrements dès qu'une connexion redevient disponible.",
    },
    {
        title: 'Suivre vos résultats',
        body: 'Le tableau de bord de chaque événement affiche les inscriptions, les paiements et les check-ins en temps réel. Toutes les listes (invités, commandes, check-ins) s’exportent en CSV depuis leur page respective.',
    },
    {
        title: 'Gérer votre organisation : plan, quotas et RGPD',
        body: "La page Facturation affiche votre consommation du mois (inscriptions, e-mails, événements actifs) par rapport au quota de votre plan. Un quota d'inscriptions dépassé ne bloque jamais un invité : les nouvelles inscriptions basculent en liste d'attente plutôt que d'être refusées. La fiche d'un contact permet à tout moment d'exporter ou d'anonymiser ses données personnelles.",
    },
];

const faqItems = [
    {
        question: 'Le check-in fonctionne-t-il vraiment sans connexion internet ?',
        answer: "Oui : c'est une contrainte non négociable du produit. L'application mobile de check-in télécharge la liste des invités avant l'événement et enregistre chaque passage localement, y compris pour un walk-in sans inscription préalable. La synchronisation avec le serveur se fait automatiquement dès qu'une connexion redevient disponible, sans jamais créer de doublon.",
    },
    {
        question: 'Que se passe-t-il si deux postes de check-in enregistrent le même invité en même temps ?',
        answer: "Le second enregistrement est reconnu comme un conflit plutôt qu'une erreur : l'invité reste marqué présent une seule fois, et les deux postes de check-in en sont informés.",
    },
    {
        question: 'Quels moyens de paiement Mobile Money sont pris en charge ?',
        answer: "Le paiement passe par un agrégateur Mobile Money couvrant plusieurs opérateurs et pays d'Afrique francophone. La liste exacte des opérateurs disponibles dépend du pays de l'acheteur au moment du paiement.",
    },
    {
        question: "Pourquoi mon modèle WhatsApp n'est-il pas encore utilisable ?",
        answer: "Chaque modèle de message WhatsApp doit être approuvé par Meta avant son premier envoi — ce délai est indépendant d'Itaza. Démarrez cette démarche dès que possible si vous comptez utiliser WhatsApp pour un événement à venir.",
    },
    {
        question: "Qu'arrive-t-il si je dépasse le quota de mon plan ?",
        answer: "Vous recevez une alerte par e-mail à 80 % puis à 100 % du quota mensuel. Au-delà, les nouvelles inscriptions basculent en liste d'attente au lieu d'être bloquées : aucune donnée n'est perdue, et tout revient à la normale au mois suivant ou dès un changement de plan.",
    },
    {
        question: 'Un invité peut-il modifier ou annuler son inscription lui-même ?',
        answer: "Oui, depuis le lien reçu par e-mail ou WhatsApp au moment de sa confirmation, sans avoir besoin de vous contacter.",
    },
    {
        question: "Comment un invité exerce-t-il son droit à l'effacement de ses données ?",
        answer: "Depuis la fiche du contact concerné, un clic exporte toutes ses données en JSON ou les anonymise définitivement. Les données comptables et les agrégats liés à l'événement sont conservés, seules les données identifiantes sont effacées.",
    },
    {
        question: 'Puis-je changer de plan à tout moment ?',
        answer: 'Oui, depuis Organisation → Facturation. Le changement est proratisé automatiquement par notre prestataire de paiement.',
    },
];

export default function Index() {
    return (
        <OrganizerLayout title="Aide" eyebrow="Ressources">
            <Head title="Aide" />

            <p className="mb-12 max-w-2xl text-ink-soft">
                Guide de démarrage, tutoriels des parcours principaux et réponses aux questions les plus fréquentes. Si vous ne trouvez
                pas ce que vous cherchez ici, contactez votre administrateur Itaza.
            </p>

            <section className="mb-14">
                <h2 className="mb-6 font-serif text-xl italic">Guide de démarrage</h2>
                <ol className="space-y-5">
                    {startSteps.map((step, index) => (
                        <li key={step.title} className="flex gap-4 rounded-card bg-bg p-5 ring-1 ring-line">
                            <span className="font-label shrink-0 text-sm text-accent">{String(index + 1).padStart(2, '0')}</span>
                            <div>
                                <p className="font-medium">{step.title}</p>
                                <p className="mt-1 text-sm text-ink-soft">{step.body}</p>
                            </div>
                        </li>
                    ))}
                </ol>
            </section>

            <section className="mb-14">
                <h2 className="mb-6 font-serif text-xl italic">Tutoriels des parcours principaux</h2>
                <div className="space-y-3">
                    {tutorials.map((tutorial) => (
                        <details key={tutorial.title} className="group rounded-card bg-bg p-5 ring-1 ring-line">
                            <summary className="cursor-pointer list-none font-medium marker:content-none">{tutorial.title}</summary>
                            <p className="mt-2 text-sm text-ink-soft">{tutorial.body}</p>
                        </details>
                    ))}
                </div>
            </section>

            <section>
                <h2 className="mb-6 font-serif text-xl italic">Questions fréquentes</h2>
                <div className="space-y-3">
                    {faqItems.map((item) => (
                        <details key={item.question} className="group rounded-card bg-bg-deep p-5">
                            <summary className="cursor-pointer list-none font-medium marker:content-none">{item.question}</summary>
                            <p className="mt-2 text-sm text-ink-soft">{item.answer}</p>
                        </details>
                    ))}
                </div>
            </section>
        </OrganizerLayout>
    );
}
