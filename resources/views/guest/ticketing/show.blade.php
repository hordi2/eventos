@extends('guest.layout')

@section('title', "Billets — {$event->title}")

@php
    $initialItems = [];
    foreach ($ticketTypes as $ticketType) {
        $initialItems[$ticketType['id']] = 0;
    }
@endphp

@section('content')
    <div
        class="mx-auto max-w-lg px-4 py-10 sm:py-16"
        x-data="{
            items: {{ Illuminate\Support\Js::from($initialItems) }},
            ticketTypes: {{ Illuminate\Support\Js::from($ticketTypes) }},
            donationAmount: '',
            zeroDecimal: ['XOF', 'XAF'],
            get currency() { return this.ticketTypes[0]?.currency ?? 'EUR'; },
            get divisor() { return this.zeroDecimal.includes(this.currency) ? 1 : 100; },
            get totalMinor() {
                let sum = 0;
                for (const t of this.ticketTypes) { sum += (this.items[t.id] || 0) * t.amount_minor; }
                const donation = parseFloat(this.donationAmount) || 0;
                sum += Math.round(donation * this.divisor);
                return sum;
            },
            get totalFormatted() {
                return (this.totalMinor / this.divisor).toLocaleString('fr-FR', { minimumFractionDigits: this.divisor === 1 ? 0 : 2 }) + ' ' + this.currency;
            },
            get hasSelection() {
                return Object.values(this.items).some((qty) => qty > 0);
            },
        }"
    >
        <p class="mb-1 text-sm font-medium text-ink-soft">{{ $event->title }}</p>
        <h1 class="mb-8 text-2xl">Billets</h1>

        @if ($errors->any())
            <div class="mb-6 rounded-control border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                @foreach ($errors->all() as $message)
                    <p>{{ $message }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('guest.ticketing.store', [request()->route('organization'), request()->route('event')]) }}" novalidate>
            @csrf
            <input type="hidden" name="checkout_token" value="{{ $checkoutToken }}">

            <div class="mb-8 space-y-4">
                @forelse ($ticketTypes as $ticketType)
                    <div class="rounded-control border border-line p-4 {{ ! $ticketType['available'] ? 'opacity-50' : '' }}">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-medium text-ink">{{ $ticketType['name'] }}</p>
                                @if ($ticketType['tier_name'])
                                    <p class="text-xs text-ink-soft">Palier : {{ $ticketType['tier_name'] }}</p>
                                @endif
                                @if ($ticketType['description'])
                                    <p class="mt-1 text-sm text-ink-soft">{{ $ticketType['description'] }}</p>
                                @endif
                                <p class="mt-1 text-sm text-ink">
                                    @if (! $ticketType['available'])
                                        <span class="text-ink-soft">Épuisé ou vente fermée</span>
                                    @else
                                        {{ $ticketType['price_label'] }}
                                    @endif
                                </p>
                            </div>

                            @if ($ticketType['available'])
                                <input
                                    type="number"
                                    name="items[{{ $ticketType['id'] }}]"
                                    x-model.number="items[{{ $ticketType['id'] }}]"
                                    min="0"
                                    max="{{ $ticketType['max_per_order'] ?? 99 }}"
                                    class="w-20 rounded-control border border-line px-2 py-1.5 text-center text-ink"
                                    aria-label="Quantité — {{ $ticketType['name'] }}"
                                >
                            @endif
                        </div>
                        @if ($ticketType['available'] && $ticketType['min_per_order'] > 1)
                            <p class="mt-2 text-xs text-ink-soft">Minimum {{ $ticketType['min_per_order'] }} par commande.</p>
                        @endif
                    </div>
                @empty
                    <p class="text-ink-soft">Aucun billet n'est disponible pour cet événement pour le moment.</p>
                @endforelse
            </div>

            <div class="mb-8">
                <label for="donation_amount" class="mb-1.5 block text-sm font-medium text-ink">Faire un don (optionnel)</label>
                <input
                    type="number"
                    id="donation_amount"
                    name="donation_amount"
                    x-model="donationAmount"
                    min="0"
                    step="0.01"
                    placeholder="Montant libre"
                    class="w-full rounded-control border border-line px-3 py-2 text-ink"
                >
                <div class="mt-2 flex gap-2">
                    <template x-for="suggested in [5, 10, 20]" :key="suggested">
                        <button type="button" @click="donationAmount = suggested" class="rounded-pill border border-line px-3 py-1 text-xs text-ink-soft hover:text-ink" x-text="suggested + ' ' + currency"></button>
                    </template>
                </div>
            </div>

            <div class="mb-6 grid grid-cols-1 gap-4">
                <div>
                    <label for="buyer_name" class="mb-1.5 block text-sm font-medium text-ink">Nom complet *</label>
                    <input type="text" id="buyer_name" name="buyer_name" value="{{ old('buyer_name') }}" required class="w-full rounded-control border border-line px-3 py-2 text-ink">
                </div>
                <div>
                    <label for="buyer_email" class="mb-1.5 block text-sm font-medium text-ink">Adresse e-mail *</label>
                    <input type="email" id="buyer_email" name="buyer_email" value="{{ old('buyer_email') }}" required class="w-full rounded-control border border-line px-3 py-2 text-ink">
                </div>
                <div>
                    <label for="buyer_phone" class="mb-1.5 block text-sm font-medium text-ink">Téléphone</label>
                    <input type="tel" id="buyer_phone" name="buyer_phone" value="{{ old('buyer_phone') }}" placeholder="+243 8xx xxx xxx" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                </div>
            </div>

            <div class="mb-6 flex items-center justify-between border-t border-line pt-4">
                <span class="text-sm text-ink-soft">Total</span>
                <span class="text-lg font-medium text-ink" x-text="totalFormatted"></span>
            </div>

            <button type="submit" :disabled="! hasSelection" :class="{ 'opacity-50 cursor-not-allowed': ! hasSelection }" class="min-h-11 w-full rounded-pill bg-ink px-8 py-3 font-medium text-bg">
                Continuer
            </button>
        </form>
    </div>
@endsection
