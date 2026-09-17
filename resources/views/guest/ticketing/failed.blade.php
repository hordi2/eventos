@extends('guest.layout')

@section('title', "Paiement non abouti — {$event->title}")

@section('content')
    {{-- Don promis dans un formulaire d'inscription : une commande sans billet (T-056). --}}
    @php($isDonation = $order->items->isEmpty() && $order->donations->isNotEmpty())
    <div class="mx-auto max-w-lg px-4 py-16 text-center">
        <p class="mb-1 text-sm font-medium text-ink-soft">{{ $event->title }}</p>
        @if ($isDonation)
            <h1 class="mb-4 text-2xl">Le paiement de votre don n'a pas abouti</h1>
            <p class="text-ink-soft">Ce don n'a pas été réglé. Contactez l'organisateur pour le finaliser.</p>
        @else
            <h1 class="mb-4 text-2xl">Le paiement n'a pas abouti</h1>
            <p class="mb-8 text-ink-soft">
                Les places de cette commande ne sont plus réservées. Vous pouvez recommencer votre commande.
            </p>
            <a href="{{ route('guest.ticketing.show', [request()->route('organization'), request()->route('event')]) }}" class="inline-flex min-h-11 items-center justify-center rounded-pill bg-ink px-8 py-3 font-medium text-bg">
                Recommencer
            </a>
        @endif
    </div>
@endsection
