@extends('guest.layout')

@section('title', "Paiement non abouti — {$event->title}")

@section('content')
    {{-- Don promis dans un formulaire d'inscription : une commande sans billet (T-056). --}}
    @php($isDonation = $order->items->isEmpty() && $order->donations->isNotEmpty())
    <div class="mx-auto max-w-lg px-4 py-16 text-center">
        <p class="mb-1 text-sm font-medium text-ink-soft">{{ $event->title }}</p>
        @if ($isDonation)
            <h1 class="mb-4 text-2xl">Le paiement de votre don n'a pas abouti</h1>
            <p class="mb-8 text-ink-soft">Vous pouvez réessayer par carte ou Mobile Money, ou choisir de le régler à l'accueil.</p>
        @else
            <h1 class="mb-4 text-2xl">Le paiement n'a pas abouti</h1>
            <p class="mb-8 text-ink-soft">
                Vous pouvez réessayer : vos places seront de nouveau réservées pendant 15 minutes, si elles sont encore disponibles.
            </p>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-control border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                @foreach ($errors->all() as $message)
                    <p>{{ $message }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('guest.ticketing.payment.retry', [request()->route('organization'), request()->route('event'), $order->reservation_key]) }}">
            @csrf
            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-pill bg-ink px-8 py-3 font-medium text-bg">
                Réessayer le paiement
            </button>
        </form>

        @unless ($isDonation)
            <a href="{{ route('guest.ticketing.show', [request()->route('organization'), request()->route('event')]) }}" class="mt-6 inline-block text-sm text-ink-soft underline">
                Choisir d'autres billets
            </a>
        @endunless
    </div>
@endsection
