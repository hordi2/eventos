@extends('guest.layout')

@section('title', "Simulation terminée — {$event->title}")

@section('content')
    <div class="mx-auto max-w-lg px-4 py-16 text-center">
        {{-- L'écran que verrait l'invité, tel que réglé dans le constructeur. --}}
        @if (! $attending)
            <h1 class="mb-4 text-2xl">{{ $settings['decline_screen']['title'] !== '' ? $settings['decline_screen']['title'] : 'Merci pour votre réponse' }}</h1>

            @if ($settings['decline_screen']['message'] !== '')
                <p class="mb-8 whitespace-pre-line text-ink-soft">{{ $settings['decline_screen']['message'] }}</p>
            @endif
        @else
            <h1 class="mb-4 text-2xl">{{ $settings['confirmation']['title'] !== '' ? $settings['confirmation']['title'] : 'Inscription confirmée' }}</h1>
            <p class="mb-8 whitespace-pre-line text-ink-soft">{{ $settings['confirmation']['message'] !== '' ? $settings['confirmation']['message'] : "Merci, votre inscription à {$event->title} est enregistrée." }}</p>
        @endif

        <section class="mb-10 rounded-card border border-line bg-bg p-5 text-left text-sm text-ink-soft">
            <h2 class="mb-2 text-lg text-ink">Simulation terminée</h2>
            <p>Rien n'a été enregistré et aucun message n'est parti. Un vrai invité verrait ici {{ $attending ? "son QR code d'entrée, et recevrait sa confirmation" : 'la confirmation de sa réponse' }}.</p>
        </section>

        <div class="flex flex-col items-center justify-center gap-3 sm:flex-row">
            @if ($restartUrl)
                <a href="{{ $restartUrl }}" class="form-button inline-flex min-h-11 items-center rounded-pill px-6 py-2.5 font-medium">Recommencer la simulation</a>
            @endif
            @if ($builderUrl)
                <a href="{{ $builderUrl }}" class="inline-flex min-h-11 items-center rounded-pill px-6 py-2.5 font-medium text-ink underline">Retour au constructeur</a>
            @endif
        </div>
    </div>
@endsection
