@extends('guest.layout')

@section('title', __('Annuaire des participants'))

@section('content')
    <div class="mx-auto max-w-lg px-4 py-16">
        <h1 class="mb-2 text-2xl">{{ __('Annuaire des participants') }}</h1>
        <p class="mb-8 text-sm text-ink-soft">{{ $event->title }}</p>

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
