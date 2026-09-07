@extends('guest.static-layout')

@section('title', "Conditions d'utilisation — " . config('app.name', 'Itaza Invitation'))

@section('content')
    <div class="border-b border-line bg-accent/5">
        <div class="mx-auto max-w-2xl px-4 py-16 sm:px-6">
            <p class="mb-3 font-label text-xs tracking-[0.28em] text-accent uppercase">Cadre légal</p>
            <h1 class="mb-2 font-serif text-4xl text-ink italic">Conditions d'utilisation</h1>
            <p class="text-sm text-ink-soft">Dernière mise à jour : {{ now()->translatedFormat('d F Y') }}</p>
        </div>
    </div>

    <div class="mx-auto max-w-2xl px-4 py-16 sm:px-6">
        <div class="mb-8 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <strong>Brouillon.</strong> Ce texte est un gabarit standard, pas encore validé par un juriste ni
            approuvé par l'entreprise. Ne pas considérer comme les conditions d'utilisation définitives tant qu'il
            n'a pas été relu et complété (raison sociale, adresse du siège, juridiction compétente...).
        </div>

        <nav class="mb-10 rounded-lg border border-line p-4 text-sm">
            <p class="mb-2 font-medium text-ink">Sommaire</p>
            <ol class="grid gap-1 sm:grid-cols-2">
                <li><a href="#objet" class="underline underline-offset-2">1. Objet</a></li>
                <li><a href="#compte" class="underline underline-offset-2">2. Compte et organisation</a></li>
                <li><a href="#eligibilite" class="underline underline-offset-2">3. Éligibilité</a></li>
                <li><a href="#utilisation" class="underline underline-offset-2">4. Utilisation acceptable</a></li>
                <li><a href="#abonnement" class="underline underline-offset-2">5. Abonnement et facturation</a></li>
                <li><a href="#billetterie" class="underline underline-offset-2">6. Billetterie et paiements</a></li>
                <li><a href="#propriete" class="underline underline-offset-2">7. Propriété intellectuelle</a></li>
                <li><a href="#tiers" class="underline underline-offset-2">8. Services tiers</a></li>
                <li><a href="#disponibilite" class="underline underline-offset-2">9. Disponibilité et responsabilité</a></li>
                <li><a href="#indemnisation" class="underline underline-offset-2">10. Indemnisation</a></li>
                <li><a href="#reclamation" class="underline underline-offset-2">11. Délai de réclamation</a></li>
                <li><a href="#resiliation" class="underline underline-offset-2">12. Résiliation</a></li>
                <li><a href="#modification" class="underline underline-offset-2">13. Modification des conditions</a></li>
                <li><a href="#droit" class="underline underline-offset-2">14. Droit applicable</a></li>
                <li><a href="#contact" class="underline underline-offset-2">15. Contact</a></li>
            </ol>
        </nav>

        <div class="space-y-8 text-ink-soft">
            <section id="objet">
                <h2 class="mb-2 text-xl text-ink">1. Objet</h2>
                <p>
                    Les présentes conditions régissent l'accès et l'utilisation de la plateforme
                    {{ config('app.name') }}, un service de gestion d'événements (invitation, inscription,
                    billetterie, check-in, analytics) édité par [raison sociale à compléter]. En créant un compte ou
                    en utilisant le service, vous acceptez ces conditions.
                </p>
            </section>

            <section id="compte">
                <h2 class="mb-2 text-xl text-ink">2. Compte et organisation</h2>
                <p>
                    Toute utilisation du service passe par la création d'un compte personnel rattaché à une
                    organisation. Vous êtes responsable de la confidentialité de vos identifiants et de toute action
                    effectuée depuis votre compte. Vous vous engagez à fournir des informations exactes lors de la
                    création de votre compte et à les maintenir à jour.
                </p>
            </section>

            <section id="eligibilite">
                <h2 class="mb-2 text-xl text-ink">3. Éligibilité</h2>
                <p>
                    Vous devez avoir la capacité juridique de conclure un contrat et être dûment autorisé·e à engager
                    l'organisation pour laquelle vous créez un compte. Si vous collectez des données concernant des
                    mineurs (par exemple pour un événement scolaire), vous êtes responsable du respect des règles
                    spécifiques applicables au traitement des données de mineurs dans votre juridiction.
                </p>
            </section>

            <section id="utilisation">
                <h2 class="mb-2 text-xl text-ink">4. Utilisation acceptable</h2>
                <p>
                    Vous vous engagez à ne pas utiliser le service pour collecter ou traiter des données personnelles
                    sans base légale valide, envoyer des communications non sollicitées (e-mail ou WhatsApp) à des
                    personnes n'ayant pas donné leur consentement, ou toute autre activité illicite ou frauduleuse,
                    notamment en matière de billetterie et de paiement.
                </p>
            </section>

            <section id="abonnement">
                <h2 class="mb-2 text-xl text-ink">5. Abonnement et facturation</h2>
                <p>
                    Le service est proposé selon différents plans, gratuits et payants, dont les caractéristiques
                    sont détaillées dans l'application. Les plans payants sont facturés par notre prestataire de
                    paiement (Stripe) selon la périodicité choisie. Un changement de plan est proratisé
                    automatiquement. Vous pouvez résilier un abonnement payant à tout moment depuis votre espace de
                    facturation ; la résiliation prend effet à la fin de la période en cours.
                </p>
            </section>

            <section id="billetterie">
                <h2 class="mb-2 text-xl text-ink">6. Billetterie et paiements</h2>
                <p>
                    Lorsque vous vendez des billets via la plateforme, les paiements sont traités par nos
                    prestataires (Stripe pour la carte bancaire, un agrégateur Mobile Money pour les paiements
                    mobiles). {{ config('app.name') }} n'a accès à aucune donnée de carte bancaire et n'intervient
                    pas dans la relation contractuelle entre vous et vos acheteurs de billets.
                </p>
            </section>

            <section id="propriete">
                <h2 class="mb-2 text-xl text-ink">7. Propriété intellectuelle</h2>
                <p>
                    Le contenu que vous créez sur la plateforme (événements, formulaires, contenus, listes de
                    contacts) reste votre propriété. Vous nous accordez une licence limitée pour l'héberger et le
                    traiter dans le seul but de vous fournir le service. La marque, le logo et le code de
                    {{ config('app.name') }} restent la propriété exclusive de [raison sociale à compléter].
                </p>
            </section>

            <section id="tiers">
                <h2 class="mb-2 text-xl text-ink">8. Services tiers</h2>
                <p>
                    Le service s'appuie sur des prestataires tiers pour certaines fonctions (paiement, envoi
                    d'e-mail, envoi de WhatsApp, hébergement). Ces prestataires opèrent selon leurs propres
                    conditions et politiques, que nous vous invitons à consulter. Nous ne saurions être tenus
                    responsables d'une interruption ou d'une défaillance imputable exclusivement à l'un de ces
                    prestataires.
                </p>
            </section>

            <section id="disponibilite">
                <h2 class="mb-2 text-xl text-ink">9. Disponibilité et limitation de responsabilité</h2>
                <p>
                    Nous mettons en œuvre des moyens raisonnables pour assurer la disponibilité et la fiabilité du
                    service (voir notre page Statut), sans garantie d'absence totale d'interruption. Dans les limites
                    permises par la loi applicable, notre responsabilité ne saurait excéder les sommes versées au
                    titre de l'abonnement au cours des douze derniers mois.
                </p>
            </section>

            <section id="indemnisation">
                <h2 class="mb-2 text-xl text-ink">10. Indemnisation</h2>
                <p>
                    Vous acceptez de nous indemniser de toute réclamation d'un tiers résultant de votre utilisation du
                    service en violation des présentes conditions ou du droit applicable, notamment en matière de
                    protection des données ou de communications non sollicitées.
                </p>
            </section>

            <section id="reclamation">
                <h2 class="mb-2 text-xl text-ink">11. Délai de réclamation</h2>
                <p>[Délai de prescription applicable à compléter selon la juridiction retenue.]</p>
            </section>

            <section id="resiliation">
                <h2 class="mb-2 text-xl text-ink">12. Résiliation</h2>
                <p>
                    Vous pouvez supprimer votre compte à tout moment depuis Paramètres → Compte. Nous pouvons
                    suspendre ou résilier l'accès en cas de violation manifeste des présentes conditions, après
                    notification préalable sauf urgence (fraude, sécurité).
                </p>
            </section>

            <section id="modification">
                <h2 class="mb-2 text-xl text-ink">13. Modification des conditions</h2>
                <p>
                    Ces conditions peuvent être mises à jour ; toute modification substantielle vous sera notifiée.
                    La poursuite de l'utilisation du service après notification vaut acceptation des nouvelles
                    conditions.
                </p>
            </section>

            <section id="droit">
                <h2 class="mb-2 text-xl text-ink">14. Droit applicable</h2>
                <p>[Juridiction et droit applicable à compléter selon le lieu d'établissement de l'entreprise.]</p>
            </section>

            <section id="contact">
                <h2 class="mb-2 text-xl text-ink">15. Contact</h2>
                <p>
                    Pour toute question relative à ces conditions, contactez-nous à
                    <a href="mailto:{{ config('mail.from.address') }}" class="underline underline-offset-2">{{ config('mail.from.address') }}</a>.
                </p>
            </section>
        </div>
    </div>
@endsection
