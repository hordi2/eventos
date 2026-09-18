@extends('guest.layout')

@section('title', "Votre invitation — {$event->title}")

@section('content')
    <div class="mx-auto max-w-sm px-4 py-16">
        <h1 class="mb-2 text-2xl">{{ $event->title }}</h1>
        <p class="mb-8 text-sm text-ink-soft">
            Cet événement est réservé aux personnes invitées. Retrouvez votre invitation avec l'adresse e-mail ou le numéro WhatsApp auquel vous l'avez reçue.
        </p>

        <form method="POST" action="{{ route('guest.registration.invitation.lookup', [request()->route('organization'), request()->route('event')]) }}">
            @csrf

            <div class="mb-6">
                <label for="identifier" class="mb-1.5 block text-sm font-medium text-ink">E-mail ou numéro WhatsApp</label>
                <input type="text" id="identifier" name="identifier" value="{{ old('identifier') }}" required autofocus autocomplete="email" placeholder="vous@exemple.com ou +243 81 234 5678" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                @error('identifier')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="form-button min-h-11 w-full rounded-pill px-8 py-3 font-medium">Retrouver mon invitation</button>
        </form>

        <p class="mt-6 text-center text-xs text-ink-soft">
            Vous avez reçu un lien personnel ? Ouvrez-le directement : il vous mène à votre invitation.
        </p>
    </div>
@endsection
