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
