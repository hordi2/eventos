@extends('guest.static-layout')

@section('title', "Partage d'événements — " . config('app.name', 'Itaza Invitation'))

@php
    $channels = [
        [
            'title' => 'Le lien de la page événement',
            'body' => "Chaque événement publié a sa propre page publique, personnalisée à votre charte graphique — partageable par lien, e-mail ou WhatsApp. Disponible depuis Événements → votre événement.",
            'icon' => 'M13.5 6.5 17 3a3 3 0 0 1 4 4l-3.5 3.5M10.5 17.5 7 21a3 3 0 0 1-4-4l3.5-3.5M8 16l8-8',
        ],
        [
            'title' => 'Le QR code du billet',
            'body' => "Chaque billet porte un QR code signé et à usage unique — scannable au check-in, y compris hors connexion depuis l'application mobile dédiée.",
            'icon' => 'M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm12 0h1v1h-1v-1Zm0 4h1v1h-1v-1Zm4-4h1v1h-1v-1Zm0 4h1v1h-1v-1Zm-2-2h1v1h-1v-1Z',
        ],
        [
            'title' => 'Les invitations e-mail et WhatsApp',
            'body' => "Envoyez vos invitations directement depuis Itaza, avec un lien de réponse individuel par invité — même niveau de priorité pour les deux canaux.",
            'icon' => 'M4 4h16v12H8l-4 4V4Z',
        ],
    ];
@endphp

@section('content')
    <div class="border-b border-line bg-accent/5">
        <div class="mx-auto max-w-2xl px-4 py-16 sm:px-6">
            <p class="mb-3 font-label text-xs tracking-[0.28em] text-accent uppercase">Partage</p>
            <h1 class="mb-4 font-serif text-4xl text-ink italic">Partager un événement</h1>
            <p class="text-ink-soft">
                Trois façons de faire connaître votre événement, déjà disponibles dans l'application — pas besoin
                d'outil externe.
            </p>
        </div>
    </div>

    <div class="mx-auto max-w-2xl px-4 py-16 sm:px-6">
        <ul class="space-y-4">
            @foreach ($channels as $channel)
                <li class="flex gap-4 rounded-card border border-line p-5">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-accent/10 text-accent">
                        <svg viewBox="0 0 24 24" class="h-5 w-5 stroke-current fill-none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <path d="{{ $channel['icon'] }}" />
                        </svg>
                    </span>
                    <div>
                        <p class="mb-1 font-medium text-ink">{{ $channel['title'] }}</p>
                        <p class="text-sm text-ink-soft">{{ $channel['body'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
@endsection
