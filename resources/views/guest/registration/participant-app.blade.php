@extends('guest.layout')

@section('title', __('Mon application'))

@section('meta')
    <link rel="manifest" href="{{ $manifestUrl }}">
    <meta name="theme-color" content="{{ $event->organization->primary_color ?? '#1b1611' }}">
    <meta name="mobile-web-app-capable" content="yes">
@endsection

@section('content')
    <div class="mx-auto max-w-lg px-4 py-16">
        <h1 class="mb-1 text-2xl">{{ $event->title }}</h1>
        <p class="mb-8 text-sm text-ink-soft">{{ __('Votre application pour cet événement') }}</p>

        {{-- Installable sur l'écran d'accueil : le bouton n'apparaît que si
             le navigateur a déclenché `beforeinstallprompt` — Safari iOS ne
             le fait jamais, « Partager → Sur l'écran d'accueil » reste le
             seul chemin là-bas, et c'est pourquoi ce bouton reste discret. --}}
        <div
            x-data="{
                deferred: null,
                visible: false,
                init() {
                    window.addEventListener('beforeinstallprompt', (event) => {
                        event.preventDefault();
                        this.deferred = event;
                        this.visible = true;
                    });
                    if ('serviceWorker' in navigator) {
                        navigator.serviceWorker.register('/service-worker.js').catch(() => {});
                    }
                },
                async install() {
                    if (!this.deferred) { return; }
                    this.deferred.prompt();
                    await this.deferred.userChoice;
                    this.deferred = null;
                    this.visible = false;
                },
            }"
            x-show="visible"
            x-cloak
            class="mb-8"
        >
            <button type="button" @click="install()" class="form-button inline-flex min-h-11 items-center rounded-pill px-6 py-2.5">
                {{ __("Installer sur l'écran d'accueil") }}
            </button>
        </div>

        @if ($hub['programme'] !== [])
            <h2 class="mb-3 font-serif text-xl italic">{{ __('Programme') }}</h2>
            <ul class="mb-10 space-y-3">
                @foreach ($hub['programme'] as $session)
                    <li class="rounded-card bg-bg px-5 py-4 ring-1 ring-line">
                        <p class="font-label text-[0.62rem] tracking-[0.2em] text-ink-soft uppercase">{{ $session['day'] }} · {{ $session['time'] }}</p>
                        <p class="mt-1 text-ink">{{ $session['title'] }}</p>
                        @if ($session['room'])
                            <p class="mt-1 text-sm text-ink-soft">{{ $session['room'] }}</p>
                        @endif
                        @if ($session['speakers'] !== [])
                            <p class="mt-1 text-sm text-ink-soft">{{ implode(', ', $session['speakers']) }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($hub['venue'])
            <h2 class="mb-3 font-serif text-xl italic">{{ __('Plan') }}</h2>
            <div class="mb-10 rounded-card bg-bg px-5 py-4 ring-1 ring-line">
                <p class="text-ink">{{ $hub['venue']['name'] }}</p>
                @if ($hub['venue']['address'])
                    <p class="mt-1 text-sm text-ink-soft">{{ $hub['venue']['address'] }}</p>
                @endif
                @if ($hub['venue']['accessInstructions'])
                    <p class="mt-2 text-sm text-ink-soft">{{ $hub['venue']['accessInstructions'] }}</p>
                @endif
                @if ($hub['venue']['parkingInfo'])
                    <p class="mt-1 text-sm text-ink-soft">{{ __('Stationnement : :info', ['info' => $hub['venue']['parkingInfo']]) }}</p>
                @endif
                <a href="{{ $hub['venue']['mapUrl'] }}" target="_blank" rel="noreferrer" class="mt-3 inline-block text-sm text-accent underline">
                    {{ __("Voir l'itinéraire") }}
                </a>
            </div>
        @endif

        @if ($directoryUrl)
            <h2 class="mb-3 font-serif text-xl italic">{{ __('Networking') }}</h2>
            <div class="mb-10 rounded-card bg-bg px-5 py-4 ring-1 ring-line">
                <p class="mb-3 text-sm text-ink-soft">{{ __("Retrouvez les autres participants, prenez rendez-vous, échangez vos badges.") }}</p>
                <a href="{{ $directoryUrl }}" class="inline-flex min-h-10 items-center rounded-pill border border-line px-4 py-2 text-sm text-ink">
                    {{ __("Ouvrir l'annuaire") }}
                </a>
            </div>
        @endif
    </div>
@endsection
