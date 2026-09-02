@extends('guest.layout')

@section('title', "Inscription confirmée — {$event->title}")

@section('content')
    <div class="mx-auto max-w-lg px-4 py-16 text-center">
        <h1 class="mb-4 text-2xl">
            @if ($registration->status->value === 'waitlisted')
                Vous êtes sur liste d'attente
            @else
                Inscription confirmée
            @endif
        </h1>

        <p class="mb-8 text-ink-soft">
            @if ($registration->status->value === 'waitlisted')
                {{ $event->title }} affiche complet pour le moment. Nous vous préviendrons si une place se libère.
            @else
                Merci, votre inscription à {{ $event->title }} est enregistrée.
            @endif
        </p>

        <p class="mb-8 text-sm text-ink-soft">Inscription enregistrée avec l'adresse {{ $registration->email }}.</p>

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
