@extends('guest.layout')

@section('title', __('Mon déplacement'))

@section('content')
    <div class="mx-auto max-w-lg px-4 py-16">
        <h1 class="mb-2 text-2xl">{{ __('Mon déplacement') }}</h1>
        <p class="mb-8 text-sm text-ink-soft">{{ $event->title }}</p>

        @if (session('status') === 'travel-declared')
            <p role="status" class="mb-6 rounded-card bg-bg px-4 py-3 text-sm text-success ring-1 ring-line">
                {{ __('Merci : votre déplacement est pris en compte dans le bilan de l’événement.') }}
            </p>
        @endif

        <form method="POST" action="{{ $formUrl }}" class="mb-10 rounded-card bg-bg p-5 ring-1 ring-line">
            @csrf

            <p class="mb-4 text-sm text-ink-soft">
                {{ __('L’organisateur mesure l’empreinte de son événement. Votre réponse ne sert qu’à cela, et personne ne voit vos réponses individuellement.') }}
            </p>

            <label for="travel_mode" class="mb-1 block text-sm text-ink">{{ __('Comment venez-vous ?') }}</label>
            <select id="travel_mode" name="travel_mode" required class="mb-4 w-full rounded-control border border-line bg-bg px-4 py-2.5 text-ink">
                @foreach ($modes as $mode)
                    <option value="{{ $mode['value'] }}" @selected($registration->travel_mode?->value === $mode['value'])>{{ $mode['label'] }}</option>
                @endforeach
            </select>

            <label for="travel_distance_km" class="mb-1 block text-sm text-ink">{{ __('Distance d’un aller, en kilomètres') }}</label>
            <input
                type="number"
                id="travel_distance_km"
                name="travel_distance_km"
                min="0"
                max="20000"
                value="{{ old('travel_distance_km', $registration->travel_distance_km) }}"
                class="mb-1 w-full rounded-control border border-line bg-bg px-4 py-2.5 text-ink"
            >
            <p class="mb-4 text-xs text-ink-soft">{{ __('Le retour est compté automatiquement.') }}</p>

            <label for="travel_city" class="mb-1 block text-sm text-ink">{{ __('D’où partez-vous ? (optionnel)') }}</label>
            <input
                type="text"
                id="travel_city"
                name="travel_city"
                maxlength="80"
                value="{{ old('travel_city', $registration->travel_city) }}"
                placeholder="{{ __('Gombe, Kinshasa') }}"
                class="mb-4 w-full rounded-control border border-line bg-bg px-4 py-2.5 text-ink"
            >

            {{-- Encouragement au covoiturage : rien n'est publié sans ce choix. --}}
            <fieldset class="mb-4">
                <legend class="mb-2 text-sm text-ink">{{ __('Covoiturage') }}</legend>
                <label class="mb-2 flex cursor-pointer items-start gap-3">
                    <input type="radio" name="carpool_role" value="" class="mt-1" @checked($registration->carpool_role === null)>
                    <span class="text-sm text-ink-soft">{{ __('Je ne souhaite pas apparaître') }}</span>
                </label>
                <label class="mb-2 flex cursor-pointer items-start gap-3">
                    <input type="radio" name="carpool_role" value="offers" class="mt-1" @checked($registration->carpool_role === 'offers')>
                    <span class="text-sm text-ink-soft">{{ __('Je propose des places dans ma voiture') }}</span>
                </label>
                <label class="flex cursor-pointer items-start gap-3">
                    <input type="radio" name="carpool_role" value="seeks" class="mt-1" @checked($registration->carpool_role === 'seeks')>
                    <span class="text-sm text-ink-soft">{{ __('Je cherche une place') }}</span>
                </label>
                <p class="mt-2 text-xs text-ink-soft">{{ __('Seuls votre prénom et votre ville de départ seraient visibles des autres participants.') }}</p>
            </fieldset>

            @error('travel_mode')
                <p class="mb-3 text-sm text-danger">{{ $message }}</p>
            @enderror

            <button type="submit" class="form-button inline-flex min-h-11 items-center rounded-pill px-6 py-2.5">
                {{ $registration->travel_declared_at === null ? __('Déclarer mon déplacement') : __('Mettre à jour') }}
            </button>
        </form>

        @if ($board['alone'] > 1)
            <p class="mb-8 rounded-card bg-bg-alt px-5 py-4 text-sm text-ink-soft">
                {{ trans_choice('{1}Une personne vient seule en voiture.|[2,*]:count personnes viennent seules en voiture — partager un trajet diviserait leur empreinte par trois.', $board['alone'], ['count' => $board['alone']]) }}
            </p>
        @endif

        @if ($board['offers'] !== [] || $board['seekers'] !== [])
            <h2 class="mb-3 font-serif text-xl italic">{{ __('Covoiturage') }}</h2>

            @if ($board['offers'] !== [])
                <p class="mb-2 font-label text-[0.62rem] tracking-[0.2em] text-ink-soft uppercase">{{ __('Proposent des places') }}</p>
                <ul class="mb-6 space-y-2">
                    @foreach ($board['offers'] as $person)
                        <li class="rounded-card bg-bg px-5 py-3 text-sm text-ink ring-1 ring-line">
                            {{ $person['name'] }}@if ($person['city']) <span class="text-ink-soft">— {{ $person['city'] }}</span>@endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($board['seekers'] !== [])
                <p class="mb-2 font-label text-[0.62rem] tracking-[0.2em] text-ink-soft uppercase">{{ __('Cherchent une place') }}</p>
                <ul class="space-y-2">
                    @foreach ($board['seekers'] as $person)
                        <li class="rounded-card bg-bg px-5 py-3 text-sm text-ink ring-1 ring-line">
                            {{ $person['name'] }}@if ($person['city']) <span class="text-ink-soft">— {{ $person['city'] }}</span>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </div>
@endsection
