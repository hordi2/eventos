@extends('guest.layout')

@section('title', "Paiement — {$event->title}")

@section('content')
    <div class="mx-auto max-w-lg px-4 py-10 sm:py-16">
        <p class="mb-1 text-sm font-medium text-ink-soft">{{ $event->title }}</p>
        <h1 class="mb-2 text-2xl">Moyen de paiement</h1>
        <p class="mb-8 text-ink-soft">Total à régler : <strong class="text-ink">{{ $order->total->format() }}</strong></p>

        @if ($errors->any())
            <div class="mb-6 rounded-control border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                @foreach ($errors->all() as $message)
                    <p>{{ $message }}</p>
                @endforeach
            </div>
        @endif

        <div class="space-y-4">
            <form method="POST" action="{{ route('guest.ticketing.payment.stripe', [request()->route('organization'), request()->route('event'), $order->reservation_key]) }}">
                @csrf
                <button type="submit" class="min-h-11 w-full rounded-pill bg-ink px-8 py-3 font-medium text-bg">
                    Payer par carte
                </button>
            </form>

            <div x-data="{ open: false }" class="rounded-control border border-line p-4">
                <button type="button" @click="open = ! open" class="w-full text-left font-medium text-ink">
                    Payer par Mobile Money
                </button>
                <div x-show="open" x-cloak class="mt-4">
                    <form method="POST" action="{{ route('guest.ticketing.payment.mobile-money', [request()->route('organization'), request()->route('event'), $order->reservation_key]) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label for="network" class="mb-1.5 block text-sm font-medium text-ink">Opérateur</label>
                            <select id="network" name="network" required class="w-full rounded-control border border-line px-3 py-2 text-ink">
                                <option value="MTN">MTN Mobile Money</option>
                                <option value="ORANGE">Orange Money</option>
                                <option value="MOOV">Moov Money</option>
                                <option value="AIRTEL">Airtel Money</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label for="country_code" class="mb-1.5 block text-sm font-medium text-ink">Indicatif</label>
                                <select id="country_code" name="country_code" required class="w-full rounded-control border border-line px-2 py-2 text-ink">
                                    <option value="243">RDC (+243)</option>
                                    <option value="242">Congo (+242)</option>
                                    <option value="237">Cameroun (+237)</option>
                                    <option value="225">Côte d'Ivoire (+225)</option>
                                    <option value="221">Sénégal (+221)</option>
                                </select>
                            </div>
                            <div class="col-span-2">
                                <label for="phone_number" class="mb-1.5 block text-sm font-medium text-ink">Numéro</label>
                                <input type="tel" id="phone_number" name="phone_number" required placeholder="8xxxxxxxx" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                            </div>
                        </div>
                        <button type="submit" class="min-h-11 w-full rounded-pill border border-ink px-8 py-3 font-medium text-ink">
                            Payer via Mobile Money
                        </button>
                    </form>
                </div>
            </div>

            <form method="POST" action="{{ route('guest.ticketing.payment.on-site', [request()->route('organization'), request()->route('event'), $order->reservation_key]) }}">
                @csrf
                <button type="submit" class="min-h-11 w-full rounded-pill border border-line px-8 py-3 font-medium text-ink">
                    Payer à l'arrivée
                </button>
            </form>
        </div>
    </div>
@endsection
