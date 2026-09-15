@extends('guest.layout')

@section('title', $registration->status->value === 'declined' ? "Réponse enregistrée — {$event->title}" : "Inscription confirmée — {$event->title}")

@section('content')
    <div class="mx-auto max-w-lg px-4 py-16 text-center">
        @if ($registration->status->value === 'declined')
            <h1 class="mb-4 text-2xl">{{ $settings['decline_screen']['title'] !== '' ? $settings['decline_screen']['title'] : 'Merci pour votre réponse' }}</h1>

            @if ($settings['decline_screen']['message'] !== '')
                <p class="mb-8 whitespace-pre-line text-ink-soft">{{ $settings['decline_screen']['message'] }}</p>
            @endif
        @elseif ($registration->status->value === 'waitlisted')
            <h1 class="mb-4 text-2xl">Vous êtes sur liste d'attente</h1>
            <p class="mb-8 text-ink-soft">{{ $event->title }} affiche complet pour le moment. Nous vous préviendrons si une place se libère.</p>
        @else
            <h1 class="mb-4 text-2xl">{{ $settings['confirmation']['title'] !== '' ? $settings['confirmation']['title'] : 'Inscription confirmée' }}</h1>
            <p class="mb-8 whitespace-pre-line text-ink-soft">{{ $settings['confirmation']['message'] !== '' ? $settings['confirmation']['message'] : "Merci, votre inscription à {$event->title} est enregistrée." }}</p>
        @endif

        {{-- Un QR d'entrée par personne, titulaire puis accompagnants (T-032). --}}
        @if ($qrCodes !== [])
            <section class="mb-10">
                <h2 class="mb-2 text-lg">{{ count($qrCodes) > 1 ? "Vos QR codes d'entrée" : "Votre QR code d'entrée" }}</h2>
                <p class="mb-6 text-sm text-ink-soft">
                    {{ count($qrCodes) > 1 ? "Chaque personne présente le sien à l'accueil, sur téléphone ou imprimé." : "Présentez-le à l'accueil, sur votre téléphone ou imprimé." }}
                </p>
                <div class="grid gap-4 {{ count($qrCodes) > 1 ? 'grid-cols-2' : 'mx-auto max-w-[220px]' }}">
                    @foreach ($qrCodes as $qrCode)
                        <figure class="rounded-card border border-line bg-bg p-3">
                            <img src="{{ $qrCode['image'] }}" alt="QR code d'entrée de {{ $qrCode['name'] !== '' ? $qrCode['name'] : 'l\'invité' }}" width="200" height="200" class="mx-auto h-auto w-full max-w-[200px]">
                            <figcaption class="mt-2 text-sm font-medium text-ink">{{ $qrCode['name'] !== '' ? $qrCode['name'] : 'Invité' }}</figcaption>
                        </figure>
                    @endforeach
                </div>
            </section>
        @endif

        <p class="mb-8 text-sm text-ink-soft">
            {{ $registration->status->value === 'declined' ? 'Réponse enregistrée' : 'Inscription enregistrée' }} avec l'adresse {{ $registration->email }}.
        </p>

        @if ($editUrl || $cancelUrl)
            <div class="flex items-center justify-center gap-6 text-sm">
                @if ($editUrl)
                    <a href="{{ $editUrl }}" class="text-accent underline">Modifier mon inscription</a>
                @endif
                @if ($cancelUrl)
                    <a href="{{ $cancelUrl }}" class="text-accent underline">Annuler mon inscription</a>
                @endif
            </div>
            <p class="mt-6 text-xs text-ink-soft">Conservez ces liens : ils vous permettent de revenir modifier ou annuler votre inscription.</p>
        @endif
    </div>
@endsection
