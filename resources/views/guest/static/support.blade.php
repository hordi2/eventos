@extends('guest.static-layout')

@section('title', 'Soutien — ' . config('app.name', 'Itaza Invitation'))

@php
    $topics = [
        [
            'title' => 'Démarrer avec Itaza',
            'body' => "Créer votre organisation, personnaliser votre charte graphique, publier votre premier événement.",
            'icon' => 'M12 3 3 8v13h6v-7h6v7h6V8L12 3Z',
        ],
        [
            'title' => 'Formulaires et inscriptions',
            'body' => 'Construire un formulaire, ajouter de la logique conditionnelle, gérer les modifications et annulations des invités.',
            'icon' => 'M6 3h9l5 5v13H6V3Zm8 1.5V9h4.5M9 13h6M9 16.5h6M9 9.5h2',
        ],
        [
            'title' => 'Billetterie et paiements',
            'body' => 'Types de billets, paiement par carte ou Mobile Money, vente sur place le jour J.',
            'icon' => 'M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4V8Z',
        ],
        [
            'title' => 'Communications',
            'body' => "Modèles d'e-mail et de WhatsApp, séquences automatisées, suivi de délivrabilité.",
            'icon' => 'M4 4h16v12H8l-4 4V4Z',
        ],
        [
            'title' => 'Check-in et jour J',
            'body' => "Application mobile hors ligne, badges, plan de table.",
            'icon' => 'M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm12 0h1v1h-1v-1Zm0 4h1v1h-1v-1Zm4-4h1v1h-1v-1Zm0 4h1v1h-1v-1Zm-2-2h1v1h-1v-1Z',
        ],
        [
            'title' => 'Compte, facturation et confidentialité',
            'body' => 'Gérer votre abonnement, vos quotas, et les droits RGPD de vos contacts.',
            'icon' => 'M12 2 4 5v6c0 5 3.4 8.7 8 11 4.6-2.3 8-6 8-11V5l-8-3Z',
        ],
    ];
@endphp

@section('content')
    <div class="border-b border-line bg-accent/5">
        <div class="mx-auto max-w-2xl px-4 py-16 sm:px-6">
            <p class="mb-3 font-label text-xs tracking-[0.28em] text-accent uppercase">Centre d'aide</p>
            <h1 class="mb-4 font-serif text-4xl text-ink italic">Comment pouvons-nous vous aider ?</h1>
            <p class="text-ink-soft">
                Une question, un blocage, un événement à préparer dans l'urgence : nous sommes là. Le guide complet,
                les tutoriels et la FAQ sont disponibles depuis votre tableau de bord, dans
                <strong>Paramètres → Aide</strong>, une fois connecté·e.
            </p>
        </div>
    </div>

    <div class="mx-auto max-w-2xl px-4 py-16 sm:px-6">
        <ul class="mb-12 grid gap-4 sm:grid-cols-2">
            @foreach ($topics as $topic)
                <li class="rounded-card border border-line p-5">
                    <span class="mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-accent/10 text-accent">
                        <svg viewBox="0 0 24 24" class="h-5 w-5 stroke-current fill-none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <path d="{{ $topic['icon'] }}" />
                        </svg>
                    </span>
                    <p class="mb-1 font-medium text-ink">{{ $topic['title'] }}</p>
                    <p class="text-sm text-ink-soft">{{ $topic['body'] }}</p>
                </li>
            @endforeach
        </ul>

        <div class="rounded-card bg-bg-alt p-6">
            <p class="mb-2 font-medium text-ink">Vous ne trouvez pas de réponse ?</p>
            <p class="text-ink-soft">
                Écrivez-nous à
                <a href="mailto:{{ config('mail.from.address') }}" class="underline underline-offset-2">{{ config('mail.from.address') }}</a>
                en décrivant votre situation le plus précisément possible (ce que vous essayiez de faire, ce qui
                s'est passé à la place, et si possible l'événement concerné) — cela nous permet de vous répondre
                plus vite. Vous pouvez aussi consulter la
                <a href="{{ route('community.index') }}" class="underline underline-offset-2">Communauté</a>
                pour voir si d'autres organisateurs ont déjà rencontré la même situation.
            </p>
        </div>
    </div>
@endsection
