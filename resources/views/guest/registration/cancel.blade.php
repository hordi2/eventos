@extends('guest.layout')

@section('title', "Annuler mon inscription — {$event->title}")

@section('content')
    <div class="mx-auto max-w-lg px-4 py-10 sm:py-16">
        <p class="mb-1 text-sm font-medium text-ink-soft">{{ $event->title }}</p>
        <h1 class="mb-8 text-2xl">Annuler mon inscription</h1>

        <p class="mb-8 text-ink-soft">
            Cette action libère votre place. Si l'événement affiche complet, elle sera automatiquement proposée à la
            personne suivante en liste d'attente.
        </p>

        <form method="POST" action="{{ url()->full() }}">
            @csrf

            <div class="mb-6">
                <label for="reason" class="mb-1.5 block text-sm font-medium text-ink">Motif (optionnel)</label>
                <textarea id="reason" name="reason" rows="3" class="w-full rounded-control border border-line px-3 py-2 text-ink"></textarea>
            </div>

            <button type="submit" class="min-h-11 w-full rounded-pill border border-line px-8 py-3 font-medium text-ink">Confirmer l'annulation</button>
        </form>
    </div>
@endsection
