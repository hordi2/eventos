@extends('guest.layout')

@section('title', __('Annuaire des participants'))

@section('content')
    <div class="mx-auto max-w-lg px-4 py-16">
        <h1 class="mb-2 text-2xl">{{ __('Annuaire des participants') }}</h1>
        <p class="mb-8 text-sm text-ink-soft">{{ $event->title }}</p>

        @if (session('status') === 'badge-met')
            <p role="status" class="mb-6 rounded-card bg-bg px-4 py-3 text-sm text-success ring-1 ring-line">
                {{ __('Rencontre enregistrée : :name.', ['name' => session('metName')]) }}
            </p>
        @elseif (session('status') === 'badge-unknown')
            <p role="status" class="mb-6 rounded-card bg-bg px-4 py-3 text-sm text-ink-soft ring-1 ring-line">
                {{ __('Ce badge ne correspond à personne de cet événement.') }}
            </p>
        @endif

        @if (session('status') === 'directory-joined')
            <p role="status" class="mb-6 rounded-card bg-bg px-4 py-3 text-sm text-success ring-1 ring-line">
                {{ __('Vous figurez dans l’annuaire. Vous pouvez en sortir quand vous voulez.') }}
            </p>
        @elseif (session('status') === 'directory-left')
            <p role="status" class="mb-6 rounded-card bg-bg px-4 py-3 text-sm text-ink-soft ring-1 ring-line">
                {{ __('Vous ne figurez plus dans l’annuaire.') }}
            </p>
        @endif

        {{-- Le consentement, donné et retiré ici même. Rien d'autre que le
             nom et cette ligne n'est publié : ni e-mail, ni téléphone. --}}
        <form method="POST" action="{{ $joinUrl }}" class="mb-10 rounded-card bg-bg p-5 ring-1 ring-line">
            @csrf

            @if ($registration->directory_consent_at === null)
                <p class="mb-1 font-medium text-ink">{{ __('Rejoindre l’annuaire') }}</p>
                <p class="mb-4 text-sm text-ink-soft">
                    {{ __('Les autres participants verront votre nom et la ligne ci-dessous. Jamais votre e-mail ni votre téléphone.') }}
                </p>

                <label for="headline" class="mb-1 block text-sm text-ink">{{ __('Une ligne pour vous présenter (optionnel)') }}</label>
                <input
                    type="text"
                    id="headline"
                    name="headline"
                    maxlength="120"
                    value="{{ old('headline') }}"
                    placeholder="{{ __('Médecin, Clinique Ngaliema') }}"
                    class="mb-4 w-full rounded-control border border-line bg-bg px-4 py-2.5 text-ink"
                >

                @error('headline')
                    <p class="mb-3 text-sm text-danger">{{ $message }}</p>
                @enderror

                <label class="mb-4 flex cursor-pointer items-start gap-3">
                    <input type="checkbox" name="shares_contact" value="1" class="mt-1 h-4 w-4 rounded border-line">
                    <span class="text-sm text-ink-soft">
                        {{ __('Partager mon adresse e-mail avec les participants que je rencontre, quand ils scannent mon badge.') }}
                    </span>
                </label>

                <input type="hidden" name="join" value="1">
                <button type="submit" class="form-button inline-flex min-h-11 items-center rounded-pill px-6 py-2.5">
                    {{ __('Je rejoins l’annuaire') }}
                </button>
            @else
                <p class="mb-1 font-medium text-ink">{{ __('Vous figurez dans l’annuaire') }}</p>
                @if ($registration->directory_headline)
                    <p class="mb-4 text-sm text-ink-soft">{{ $registration->directory_headline }}</p>
                @endif

                <input type="hidden" name="join" value="0">
                <button type="submit" class="inline-flex min-h-11 items-center rounded-pill border border-line px-6 py-2.5 text-sm text-ink">
                    {{ __('Sortir de l’annuaire') }}
                </button>
            @endif
        </form>

        @if ($badgeImageUrl)
            {{-- Le badge : c'est lui qu'on présente à quelqu'un pour qu'il le
                 scanne. Il ne porte pas le code d'entrée, qui sert à l'accueil. --}}
            <div class="mb-10 rounded-card bg-bg p-5 text-center ring-1 ring-line">
                <p class="mb-1 font-medium text-ink">{{ __('Mon badge') }}</p>
                <p class="mb-4 text-sm text-ink-soft">
                    {{ __('Faites-le scanner par la personne que vous rencontrez : vous vous retrouverez tous les deux dans vos rencontres.') }}
                </p>
                <img src="{{ $badgeImageUrl }}" alt="{{ __('Mon badge') }}" width="200" height="200" class="mx-auto h-48 w-48 rounded-card bg-bg p-2 ring-1 ring-line">
            </div>
        @endif

        @if ($connections !== [])
            <h2 class="mb-3 font-serif text-xl italic">{{ __('Mes rencontres') }}</h2>
            <ul class="mb-10 space-y-3">
                @foreach ($connections as $connection)
                    <li class="rounded-card bg-bg px-5 py-4 ring-1 ring-line">
                        <p class="text-ink">{{ $connection['name'] }}</p>
                        @if ($connection['headline'])
                            <p class="mt-1 text-sm text-ink-soft">{{ $connection['headline'] }}</p>
                        @endif
                        @if ($connection['email'])
                            <p class="mt-1 text-sm"><a href="mailto:{{ $connection['email'] }}" class="text-accent underline">{{ $connection['email'] }}</a></p>
                        @else
                            <p class="mt-1 text-xs text-ink-soft">{{ __('Cette personne n’a pas partagé son adresse.') }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        <h2 class="mb-3 font-serif text-xl italic">{{ __('Participants') }}</h2>

        @if ($attendees === [])
            <p class="rounded-card border border-dashed border-line px-5 py-10 text-center text-sm text-ink-soft">
                {{ __('Personne ne s’y est encore inscrit. Soyez le premier.') }}
            </p>
        @else
            <ul class="space-y-3">
                @foreach ($attendees as $attendee)
                    <li class="rounded-card bg-bg px-5 py-4 ring-1 ring-line">
                        <p class="text-ink">{{ $attendee['name'] }}</p>
                        @if ($attendee['headline'])
                            <p class="mt-1 text-sm text-ink-soft">{{ $attendee['headline'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
