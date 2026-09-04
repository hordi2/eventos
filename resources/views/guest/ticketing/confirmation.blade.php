@extends('guest.layout')

@section('title', "Confirmation — {$event->title}")

@section('content')
    <div class="mx-auto max-w-lg px-4 py-10 sm:py-16">
        <p class="mb-1 text-sm font-medium text-ink-soft">{{ $event->title }}</p>

        @if ($order->status->value === 'paid')
            <h1 class="mb-2 text-2xl">Commande confirmée</h1>
            <p class="mb-8 text-ink-soft">Merci {{ $order->buyer_name }}, votre paiement a bien été reçu.</p>
        @elseif ($order->status->value === 'payment_on_site')
            <h1 class="mb-2 text-2xl">Réservation confirmée</h1>
            <p class="mb-8 text-ink-soft">
                Merci {{ $order->buyer_name }}, votre place est réservée. Réglez le montant de
                <strong class="text-ink">{{ $order->total->format() }}</strong> à votre arrivée.
            </p>
        @elseif ($order->status->value === 'refunded')
            <h1 class="mb-2 text-2xl">Commande remboursée</h1>
            <p class="mb-8 text-ink-soft">Cette commande a été remboursée.</p>
        @else
            <h1 class="mb-2 text-2xl">Commande</h1>
        @endif

        <div class="mb-8 space-y-4">
            @foreach ($order->items as $item)
                <div class="rounded-control border border-line p-4">
                    <div class="flex items-center justify-between">
                        <p class="font-medium text-ink">{{ $item->name }} × {{ $item->quantity }}</p>
                        <p class="text-ink-soft">{{ $item->unit_amount->multipliedBy($item->quantity)->format() }}</p>
                    </div>

                    @if ($order->status->value === 'paid' && $item->tickets->isNotEmpty())
                        <div class="mt-3 space-y-2 border-t border-line pt-3">
                            @foreach ($item->tickets as $ticket)
                                <a
                                    href="{{ route('guest.ticketing.payment.ticket', [request()->route('organization'), request()->route('event'), $order->reservation_key, $ticket->id]) }}"
                                    class="flex items-center justify-between text-sm text-ink underline underline-offset-2"
                                >
                                    <span>Billet #{{ $ticket->id }}</span>
                                    <span>Télécharger le PDF</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach

            @foreach ($order->donations as $donation)
                <div class="rounded-control border border-line p-4">
                    <p class="font-medium text-ink">Don</p>
                    <p class="text-ink-soft">{{ $donation->amount->format() }}</p>
                </div>
            @endforeach
        </div>

        <div class="flex items-center justify-between border-t border-line pt-4">
            <span class="text-sm text-ink-soft">Total</span>
            <span class="text-lg font-medium text-ink">{{ $order->total->format() }}</span>
        </div>
    </div>
@endsection
