@extends('guest.layout')

@section('title', __('Mon application'))

@section('meta')
    <link rel="manifest" href="{{ $manifestUrl }}">
    <meta name="theme-color" content="{{ $event->organization->primary_color ?? '#1b1611' }}">
    <meta name="mobile-web-app-capable" content="yes">
    {{-- Seule cette page fait une requête fetch() côté invité (abonnement
         push) : le jeton CSRF reste local à cette vue plutôt que global à
         resources/views/guest/layout.blade.php. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
                installVisible: false,
                vapidPublicKey: @js($vapidPublicKey),
                subscribeUrl: @js($pushSubscribeUrl),
                unsubscribeUrl: @js($pushUnsubscribeUrl),
                subscribed: false,
                pushSupported: 'serviceWorker' in navigator && 'PushManager' in window,
                pushBusy: false,
                async init() {
                    window.addEventListener('beforeinstallprompt', (event) => {
                        event.preventDefault();
                        this.deferred = event;
                        this.installVisible = true;
                    });
                    if (!('serviceWorker' in navigator)) { return; }
                    try {
                        await navigator.serviceWorker.register('/service-worker.js');
                        if (this.vapidPublicKey) {
                            const registration = await navigator.serviceWorker.ready;
                            const existing = await registration.pushManager.getSubscription();
                            this.subscribed = existing !== null;
                        }
                    } catch (error) {}
                },
                async install() {
                    if (!this.deferred) { return; }
                    this.deferred.prompt();
                    await this.deferred.userChoice;
                    this.deferred = null;
                    this.installVisible = false;
                },
                urlBase64ToUint8Array(base64) {
                    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
                    const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
                    return Uint8Array.from([...raw].map((char) => char.charCodeAt(0)));
                },
                async postSubscription(url, endpoint) {
                    await fetch(url, {
                        method: url === this.subscribeUrl ? 'POST' : 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        },
                        body: JSON.stringify({ endpoint }),
                    });
                },
                async toggleNotifications() {
                    if (this.pushBusy) { return; }
                    this.pushBusy = true;
                    try {
                        const registration = await navigator.serviceWorker.ready;
                        if (this.subscribed) {
                            const existing = await registration.pushManager.getSubscription();
                            if (existing) {
                                await this.postSubscription(this.unsubscribeUrl, existing.endpoint);
                                await existing.unsubscribe();
                            }
                            this.subscribed = false;
                        } else {
                            const permission = await Notification.requestPermission();
                            if (permission !== 'granted') { return; }
                            const subscription = await registration.pushManager.subscribe({
                                userVisibleOnly: true,
                                applicationServerKey: this.urlBase64ToUint8Array(this.vapidPublicKey),
                            });
                            await this.postSubscription(this.subscribeUrl, subscription.endpoint);
                            this.subscribed = true;
                        }
                    } catch (error) {} finally {
                        this.pushBusy = false;
                    }
                },
            }"
            class="mb-8 space-y-3"
        >
            <div x-show="installVisible" x-cloak>
                <button type="button" @click="install()" class="form-button inline-flex min-h-11 items-center rounded-pill px-6 py-2.5">
                    {{ __("Installer sur l'écran d'accueil") }}
                </button>
            </div>

            <div x-show="pushSupported && vapidPublicKey" x-cloak>
                <button
                    type="button"
                    @click="toggleNotifications()"
                    :disabled="pushBusy"
                    class="inline-flex min-h-10 items-center rounded-pill border border-line px-4 py-2 text-sm text-ink disabled:opacity-50"
                >
                    <span x-show="!subscribed">{{ __('Activer les notifications') }}</span>
                    <span x-show="subscribed" x-cloak>{{ __('Désactiver les notifications') }}</span>
                </button>
            </div>
        </div>

        @if ($hub['notifications'] !== [])
            <h2 class="mb-3 font-serif text-xl italic">{{ __('Notifications') }}</h2>
            <ul class="mb-10 space-y-3">
                @foreach ($hub['notifications'] as $notification)
                    <li class="rounded-card bg-bg px-5 py-4 ring-1 ring-line">
                        <p class="text-ink">{{ $notification['title'] }}</p>
                        <p class="mt-1 text-sm text-ink-soft">{{ $notification['body'] }}</p>
                    </li>
                @endforeach
            </ul>
        @endif

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

        @if ($questionsUrl)
            <h2 class="mb-3 font-serif text-xl italic">{{ __('Questions au conférencier') }}</h2>
            <div class="mb-10 rounded-card bg-bg px-5 py-4 ring-1 ring-line">
                <p class="mb-3 text-sm text-ink-soft">{{ __('Posez votre question, votez pour celles des autres.') }}</p>
                <a href="{{ $questionsUrl }}" class="inline-flex min-h-10 items-center rounded-pill border border-line px-4 py-2 text-sm text-ink">
                    {{ __('Poser une question') }}
                </a>
            </div>
        @endif

        @if ($pollsUrl)
            <h2 class="mb-3 font-serif text-xl italic">{{ __('Sondages') }}</h2>
            <div class="mb-10 rounded-card bg-bg px-5 py-4 ring-1 ring-line">
                <p class="mb-3 text-sm text-ink-soft">{{ __('Votez en direct pendant l\'événement.') }}</p>
                <a href="{{ $pollsUrl }}" class="inline-flex min-h-10 items-center rounded-pill border border-line px-4 py-2 text-sm text-ink">
                    {{ __('Voir les sondages') }}
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
