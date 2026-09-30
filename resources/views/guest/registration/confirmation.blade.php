@extends('guest.layout')

@section('title', ($registration->status->value === 'declined' ? __('Réponse enregistrée') : __('Inscription confirmée')).' — '.$event->title)

@section('content')
    <div class="mx-auto max-w-lg px-4 py-16 text-center">
        @if ($registration->status->value === 'pending')
            {{-- Validation manuelle : la place est retenue, la confirmation viendra de l'organisateur. --}}
            <h1 class="mb-4 text-2xl">{{ __('Votre demande est bien arrivée') }}</h1>
            <p class="mb-8 text-ink-soft">
                {{ __(":event demande une validation : votre place est retenue le temps que l'organisateur examine votre demande. Vous recevrez un message dès qu'elle sera acceptée.", ['event' => $event->title]) }}
            </p>
        @elseif ($registration->status->value === 'declined')
            <h1 class="mb-4 text-2xl">{{ $settings['decline_screen']['title'] !== '' ? $settings['decline_screen']['title'] : __('Merci pour votre réponse') }}</h1>

            @if ($settings['decline_screen']['message'] !== '')
                <p class="mb-8 whitespace-pre-line text-ink-soft">{{ $settings['decline_screen']['message'] }}</p>
            @endif
        @elseif ($registration->status->value === 'waitlisted')
            <h1 class="mb-4 text-2xl">{{ __("Vous êtes sur liste d'attente") }}</h1>
            <p class="mb-8 text-ink-soft">{{ __(':event affiche complet pour le moment. Nous vous préviendrons si une place se libère.', ['event' => $event->title]) }}</p>
        @else
            <h1 class="mb-4 text-2xl">{{ $settings['confirmation']['title'] !== '' ? $settings['confirmation']['title'] : __('Inscription confirmée') }}</h1>
            <p class="mb-8 whitespace-pre-line text-ink-soft">{{ $settings['confirmation']['message'] !== '' ? $settings['confirmation']['message'] : __('Merci, votre inscription à :event est enregistrée.', ['event' => $event->title]) }}</p>
        @endif

        {{-- Don promis dans le formulaire, réglé après l'inscription (T-056). --}}
        @if ($donation !== null)
            <section class="mb-10 rounded-card border border-line p-5 text-left">
                <h2 class="mb-1 text-lg">{{ __('Votre don') }}</h2>
                <p class="text-2xl font-medium text-ink">{{ $donation['amount'] }}</p>
                @if ($donation['cause'])
                    <p class="mt-1 text-sm text-ink-soft">{{ __('Au profit de : :cause', ['cause' => $donation['cause']]) }}</p>
                @endif

                @if ($donation['status'] === 'pending')
                    <p class="mt-3 text-sm text-ink-soft">{{ __("Réglez-le maintenant par carte ou Mobile Money, ou choisissez de le régler à l'accueil.") }}</p>
                    <a href="{{ $donation['paymentUrl'] }}" class="form-button mt-4 inline-flex min-h-11 items-center rounded-pill px-6 py-2.5 font-medium">{{ __('Finaliser mon don') }}</a>
                @elseif ($donation['status'] === 'failed')
                    <p class="mt-3 text-sm text-ink-soft">{{ __("Le paiement de votre don n'a pas abouti. Vous pouvez réessayer.") }}</p>
                    <a href="{{ $donation['paymentUrl'] }}" class="form-button mt-4 inline-flex min-h-11 items-center rounded-pill px-6 py-2.5 font-medium">{{ __('Réessayer le paiement') }}</a>
                @elseif ($donation['status'] === 'payment_on_site')
                    <p class="mt-3 text-sm text-ink-soft">{{ __("Promesse enregistrée : vous le réglerez à l'accueil le jour de l'événement.") }}</p>
                @elseif ($donation['status'] === 'paid')
                    <p class="mt-3 text-sm text-ink-soft">{{ __('Merci ! Votre don est réglé et votre reçu vous a été envoyé par e-mail.') }}</p>
                @else
                    <p class="mt-3 text-sm text-ink-soft">{{ __("Ce don n'a pas été réglé. Contactez l'organisateur pour le finaliser.") }}</p>
                @endif
            </section>
        @endif

        {{-- Un QR d'entrée par personne, titulaire puis accompagnants (T-032). --}}
        @if ($qrCodes !== [])
            <section class="mb-10">
                <h2 class="mb-2 text-lg">{{ count($qrCodes) > 1 ? __("Vos QR codes d'entrée") : __("Votre QR code d'entrée") }}</h2>
                <p class="mb-6 text-sm text-ink-soft">
                    {{ count($qrCodes) > 1 ? __("Chaque personne présente le sien à l'accueil, sur téléphone ou imprimé.") : __("Présentez-le à l'accueil, sur votre téléphone ou imprimé.") }}
                </p>
                <div class="grid gap-4 {{ count($qrCodes) > 1 ? 'grid-cols-2' : 'mx-auto max-w-[220px]' }}">
                    @foreach ($qrCodes as $qrCode)
                        <figure class="rounded-card border border-line bg-bg p-3">
                            <img src="{{ $qrCode['image'] }}" alt="{{ __("QR code d'entrée de :name", ['name' => $qrCode['name'] !== '' ? $qrCode['name'] : __("l'invité")]) }}" width="200" height="200" class="mx-auto h-auto w-full max-w-[200px]">
                            <figcaption class="mt-2 text-sm font-medium text-ink">{{ $qrCode['name'] !== '' ? $qrCode['name'] : __('Invité') }}</figcaption>
                        </figure>
                    @endforeach
                </div>
            </section>
        @endif

        <p class="mb-8 text-sm text-ink-soft">
            @php($recorded = match ($registration->status->value) { 'declined' => __('Réponse enregistrée'), 'pending' => __('Demande enregistrée'), default => __('Inscription enregistrée') })
            @if ($registration->email !== '')
                {{ __(":recorded avec l'adresse :contact.", ['recorded' => $recorded, 'contact' => $registration->email]) }}
            @elseif ($registration->phone_e164)
                {{ __(':recorded avec le numéro :contact.', ['recorded' => $recorded, 'contact' => $registration->phone_e164]) }}
            @else
                {{ $recorded }}.
            @endif
        </p>

        {{-- Ajouter à mon agenda : Google d'un côté, fichier .ics pour tous les autres. --}}
        @if ($registration->status->value === 'confirmed' && isset($calendar))
            <div class="mb-8 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ $calendar['google'] }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-pill border border-line px-5 py-2.5 text-sm font-medium text-ink">
                    {{ __('Ajouter à Google Agenda') }}
                </a>
                <a href="{{ $calendar['ics'] }}" class="inline-flex min-h-11 items-center rounded-pill border border-line px-5 py-2.5 text-sm font-medium text-ink">
                    {{ __('Ajouter à mon agenda (.ics)') }}
                </a>
            </div>
        @endif

        {{-- Programme personnel (D6) : seulement pour qui a choisi des sessions. --}}
        @if (($agendaUrl ?? null) && $registration->status->value !== 'declined')
            <div class="mb-8 flex justify-center">
                <a href="{{ $agendaUrl }}" class="inline-flex min-h-11 items-center rounded-pill border border-line px-5 py-2.5 text-sm font-medium text-ink">
                    {{ __('Voir mon programme') }}
                </a>
            </div>
        @endif

        @if ($editUrl || $cancelUrl)
            <div class="flex items-center justify-center gap-6 text-sm">
                @if ($editUrl)
                    <a href="{{ $editUrl }}" class="text-accent underline">{{ __('Modifier mon inscription') }}</a>
                @endif
                @if ($cancelUrl)
                    <a href="{{ $cancelUrl }}" class="text-accent underline">{{ __('Annuler mon inscription') }}</a>
                @endif
            </div>
            <p class="mt-6 text-xs text-ink-soft">{{ __('Conservez ces liens : ils vous permettent de revenir modifier ou annuler votre inscription.') }}</p>
        @endif
    </div>
@endsection
