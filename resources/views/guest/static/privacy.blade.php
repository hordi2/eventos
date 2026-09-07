@extends('guest.layout')

@section('title', 'Politique de confidentialité — ' . config('app.name', 'Itaza Invitation'))

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-16">
        <div class="mb-8 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <strong>Brouillon.</strong> Ce texte est un gabarit standard, pas encore validé par un juriste ni
            approuvé par l'entreprise. Ne pas considérer comme la politique de confidentialité définitive tant
            qu'elle n'a pas été relue et complétée (raison sociale, adresse du siège, délégué à la protection des
            données...). Le tableau des traitements ci-dessous reflète en revanche les traitements réellement
            effectués par le produit — il est généré automatiquement, jamais écrit à la main.
        </div>

        <h1 class="mb-2 text-3xl">Politique de confidentialité</h1>
        <p class="mb-10 text-sm text-ink-soft">Dernière mise à jour : {{ now()->translatedFormat('d F Y') }}</p>

        <div class="space-y-8 text-ink-soft">
            <section>
                <h2 class="mb-2 text-xl text-ink">Qui sommes-nous</h2>
                <p>
                    {{ config('app.name') }} est édité par [raison sociale à compléter]. Cette politique explique
                    quelles données sont collectées lorsque vous utilisez la plateforme, en tant qu'organisateur ou
                    en tant qu'invité à un événement, et comment elles sont traitées.
                </p>
            </section>

            <section>
                <h2 class="mb-2 text-xl text-ink">Ce que nous traitons, et pourquoi</h2>
                <p class="mb-4">
                    Ce tableau est le registre des traitements de l'application (également consultable, avec export
                    PDF, par tout organisateur depuis Paramètres → Journal d'audit → Registre des traitements) :
                </p>
                <div class="overflow-x-auto rounded-lg ring-1 ring-line">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-bg-alt text-xs text-ink-soft uppercase">
                            <tr>
                                <th class="p-3">Finalité</th>
                                <th class="p-3">Données</th>
                                <th class="p-3">Base légale</th>
                                <th class="p-3">Conservation</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($activities as $activity)
                                <tr class="border-t border-line align-top">
                                    <td class="p-3 font-medium text-ink">{{ $activity['finalite'] }}</td>
                                    <td class="p-3">{{ $activity['donnees'] }}</td>
                                    <td class="p-3">{{ $activity['base_legale'] }}</td>
                                    <td class="p-3">{{ $activity['conservation'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section>
                <h2 class="mb-2 text-xl text-ink">Qui reçoit ces données</h2>
                <p>
                    Vos données ne sont jamais vendues. Elles sont partagées uniquement avec l'organisateur de
                    l'événement auquel vous êtes lié, et avec les prestataires techniques strictement nécessaires au
                    fonctionnement du service : hébergement, envoi d'e-mail (Postmark, Resend ou AWS SES selon la
                    configuration), envoi de WhatsApp (Twilio), paiement par carte (Stripe), paiement Mobile Money
                    (Flutterwave), suivi des erreurs applicatives (Sentry). Chacun de ces prestataires n'a accès qu'aux
                    données strictement nécessaires à sa mission.
                </p>
            </section>

            <section>
                <h2 class="mb-2 text-xl text-ink">Vos droits</h2>
                <p>
                    Conformément au RGPD, vous disposez d'un droit d'accès, de rectification et d'effacement de vos
                    données. Pour un contact d'un organisateur, l'export complet (JSON) et l'anonymisation sont
                    disponibles en libre-service depuis la fiche du contact. Pour un compte organisateur, l'export et
                    la suppression du compte sont disponibles depuis Paramètres → Compte. Vous pouvez aussi nous
                    écrire directement à
                    <a href="mailto:{{ config('mail.from.address') }}" class="underline underline-offset-2">{{ config('mail.from.address') }}</a>.
                </p>
            </section>

            <section>
                <h2 class="mb-2 text-xl text-ink">Cookies</h2>
                <p>
                    La plateforme utilise uniquement des cookies strictement nécessaires à son fonctionnement (session
                    de connexion, protection contre la falsification de requêtes). Aucun cookie publicitaire ou de
                    suivi tiers n'est déposé.
                </p>
            </section>

            <section>
                <h2 class="mb-2 text-xl text-ink">Sécurité</h2>
                <p>
                    Les données de chaque organisation sont cloisonnées au niveau applicatif et au niveau de la base
                    de données (sécurité au niveau ligne), avec chiffrement en transit. Aucune donnée de carte
                    bancaire ne transite ni n'est stockée par la plateforme — les paiements sont délégués à nos
                    prestataires certifiés.
                </p>
            </section>

            <section>
                <h2 class="mb-2 text-xl text-ink">Modifications</h2>
                <p>
                    Cette politique peut être mise à jour ; toute modification substantielle vous sera notifiée.
                </p>
            </section>

            <section>
                <h2 class="mb-2 text-xl text-ink">Contact</h2>
                <p>
                    Pour toute question relative à vos données personnelles, contactez-nous à
                    <a href="mailto:{{ config('mail.from.address') }}" class="underline underline-offset-2">{{ config('mail.from.address') }}</a>.
                </p>
            </section>
        </div>
    </div>
@endsection
