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

        <p class="text-sm text-ink-soft">Inscription enregistrée avec l'adresse {{ $registration->email }}.</p>
    </div>
@endsection
