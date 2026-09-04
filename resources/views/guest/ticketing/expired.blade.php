@extends('guest.layout')

@section('title', "Réservation expirée — {$event->title}")

@section('content')
    <div class="mx-auto max-w-lg px-4 py-16 text-center">
        <p class="mb-1 text-sm font-medium text-ink-soft">{{ $event->title }}</p>
        <h1 class="mb-4 text-2xl">Votre réservation a expiré</h1>
        <p class="mb-8 text-ink-soft">
            La confirmation de paiement n'est pas arrivée à temps et le stock a été relâché. Vous pouvez recommencer
            votre commande.
        </p>
        <a href="{{ route('guest.ticketing.show', [request()->route('organization'), request()->route('event')]) }}" class="inline-flex min-h-11 items-center justify-center rounded-pill bg-ink px-8 py-3 font-medium text-bg">
            Recommencer
        </a>
    </div>
@endsection
