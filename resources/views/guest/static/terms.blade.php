@extends('guest.layout')

@section('title', "Conditions d'utilisation — " . config('app.name', 'Itaza Invitation'))

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-16">
        <div class="mb-8 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <strong>Brouillon.</strong> Ce texte est un gabarit standard, pas encore validé par un juriste ni
            approuvé par l'entreprise. Ne pas considérer comme les conditions d'utilisation définitives tant qu'il
            n'a pas été relu et complété (raison sociale, adresse du siège, juridiction compétente...).
        </div>

        <h1 class="mb-2 text-3xl">Conditions d'utilisation</h1>
        <p class="mb-10 text-sm text-ink-soft">Dernière mise à jour : {{ now()->translatedFormat('d F Y') }}</p>

        <div class="space-y-8 text-ink-soft">
            <section>
                <h2 class="mb-2 text-xl text-ink">1. Objet</h2>
                <p>
                    Les présentes conditions régissent l'accès et l'utilisation de la plateforme
                    {{ config('app.name') }}, un service de gestion d'événements (invitation, inscription,
                    billetterie, check-in, analytics) édité par [raison sociale à compléter]. En créant un compte ou
                    en utilisant le service, vous acceptez ces conditions.
                </p>
            </section>

            <section>
                <h2 class="mb-2 text-xl text-ink">2. Compte et organisation</h2>
                <p>
                    Toute utilisation du service passe par la création d'un compte personnel rattaché à une
                    organisation. Vous êtes responsable de la confidentialité de vos identifiants et de toute action
                    effectuée depuis votre compte. Vous vous engagez à fournir des informations exactes lors de la
                    création de votre compte et à les maintenir à jour.
                </p>
            </section>

            <section>
                <h2 class="mb-2 text-xl text-ink">3. Utilisation acceptable</h2>
                <p>
                    Vous vous engagez à ne pas utiliser le service pour collecter ou traiter des données personnelles
                    sans base légale valide, envoyer des communications non sollicitées (e-mail ou WhatsApp) à des
                    personnes n'ayant pas donné leur consentement, ou toute autre activité illicite ou frauduleuse,
                    notamment en matière de billetterie et de paiement.
                </p>
            </section>

            <section>
                <h2 class="mb-2 text-xl text-ink">4. Abonnement et facturation</h2>
                <p>
                    Le service est proposé selon différents plans, gratuits et payants, dont les caractéristiques
                    sont détaillées dans l'application. Les plans payants sont facturés par notre prestataire de
                    paiement (Stripe) selon la périodicité choisie. Un changement de plan est proratisé
                    automatiquement. Vous pouvez résilier un abonnement payant à tout moment depuis votre espace de
                    facturation ; la résiliation prend effet à la fin de la période en cours.
                </p>
            </section>

            <section>
                <h2 class="mb-2 text-xl text-ink">5. Billetterie et paiements</h2>
                <p>
                    Lorsque vous vendez des billets via la plateforme, les paiements sont traités par nos
                    prestataires (Stripe pour la carte bancaire, un agrégateur Mobile Money pour les paiements
                    mobiles). {{ config('app.name') }} n'a accès à aucune donnée de carte bancaire et n'intervient
                    pas dans la relation contractuelle entre vous et vos acheteurs de billets.
                </p>
            </section>

            <section>
                <h2 class="mb-2 text-xl text-ink">6. Propriété intellectuelle</h2>
                <p>
                    Le contenu que vous créez sur la plateforme (événements, formulaires, contenus, listes de
                    contacts) reste votre propriété. Vous nous accordez une licence limitée pour l'héberger et le
                    traiter dans le seul but de vous fournir le service. La marque, le logo et le code de
                    {{ config('app.name') }} restent la propriété exclusive de [raison sociale à compléter].
                </p>
            </section>

            <section>
                <h2 class="mb-2 text-xl text-ink">7. Disponibilité et limitation de responsabilité</h2>
                <p>
                    Nous mettons en œuvre des moyens raisonnables pour assurer la disponibilité et la fiabilité du
                    service (voir notre page Statut), sans garantie d'absence totale d'interruption. Dans les limites
                    permises par la loi applicable, notre responsabilité ne saurait excéder les sommes versées au
                    titre de l'abonnement au cours des douze derniers mois.
                </p>
            </section>

            <section>
                <h2 class="mb-2 text-xl text-ink">8. Résiliation</h2>
                <p>
                    Vous pouvez supprimer votre compte à tout moment depuis Paramètres → Compte. Nous pouvons
                    suspendre ou résilier l'accès en cas de violation manifeste des présentes conditions, après
                    notification préalable sauf urgence (fraude, sécurité).
                </p>
            </section>

            <section>
                <h2 class="mb-2 text-xl text-ink">9. Modification des conditions</h2>
                <p>
                    Ces conditions peuvent être mises à jour ; toute modification substantielle vous sera notifiée.
                    La poursuite de l'utilisation du service après notification vaut acceptation des nouvelles
                    conditions.
                </p>
            </section>

            <section>
                <h2 class="mb-2 text-xl text-ink">10. Droit applicable</h2>
                <p>[Juridiction et droit applicable à compléter selon le lieu d'établissement de l'entreprise.]</p>
            </section>

            <section>
                <h2 class="mb-2 text-xl text-ink">11. Contact</h2>
                <p>
                    Pour toute question relative à ces conditions, contactez-nous à
                    <a href="mailto:{{ config('mail.from.address') }}" class="underline underline-offset-2">{{ config('mail.from.address') }}</a>.
                </p>
            </section>
        </div>
    </div>
@endsection
