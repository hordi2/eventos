@extends('guest.static-layout')

@section('title', "Programme d'affiliation — " . config('app.name', 'Itaza Invitation'))

@php
    $steps = [
        ['title' => 'Rejoindre', 'body' => 'Vous nous contactez et nous présentez comment vous comptez recommander Itaza.'],
        ['title' => 'Partager', 'body' => 'Vous recevez un lien de recommandation unique à partager avec votre réseau.'],
        ['title' => 'Suivre', 'body' => 'Vous suivez les organisations inscrites grâce à votre lien.'],
        ['title' => 'Être rémunéré·e', 'body' => 'Vous touchez une commission sur les abonnements payants générés — modalités à venir.'],
    ];
@endphp

@section('content')
    <div class="border-b border-line bg-accent/5">
        <div class="mx-auto max-w-2xl px-4 py-16 sm:px-6">
            <p class="mb-3 font-label text-xs tracking-[0.28em] text-accent uppercase">Bientôt disponible</p>
            <h1 class="mb-4 font-serif text-4xl text-ink italic">Programme d'affiliation</h1>
            <p class="text-ink-soft">
                Un programme permettant de recommander Itaza et d'être rémunéré·e pour chaque organisation apportée
                n'est pas encore en place. Voici comment il fonctionnera :
            </p>
        </div>
    </div>

    <div class="mx-auto max-w-2xl px-4 py-16 sm:px-6">
        <ol class="mb-12 grid gap-4 sm:grid-cols-2">
            @foreach ($steps as $index => $step)
                <li class="rounded-card border border-line p-5">
                    <span class="mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-accent/10 font-serif text-lg text-accent italic">
                        {{ $index + 1 }}
                    </span>
                    <p class="mb-1 font-medium text-ink">{{ $step['title'] }}</p>
                    <p class="text-sm text-ink-soft">{{ $step['body'] }}</p>
                </li>
            @endforeach
        </ol>

        <div class="rounded-card bg-bg-alt p-6 text-center">
            <p class="mb-2 font-medium text-ink">Vous animez une communauté d'organisateurs d'événements ?</p>
            <p class="mb-4 text-sm text-ink-soft">Écrivez-nous pour être informé·e dès le lancement du programme.</p>
            <a href="mailto:{{ config('mail.from.address') }}" class="inline-flex rounded-pill bg-ink px-6 py-3 text-sm text-bg">
                {{ config('mail.from.address') }}
            </a>
        </div>
    </div>
@endsection
