@extends('guest.layout')

@section('title', "Accès protégé — {$event->title}")

@section('content')
    <div class="mx-auto max-w-sm px-4 py-16">
        <h1 class="mb-2 text-2xl">{{ $event->title }}</h1>
        <p class="mb-8 text-sm text-ink-soft">Cet événement est protégé par un mot de passe.</p>

        <form method="POST" action="{{ route('guest.registration.password.verify', [request()->route('organization'), request()->route('event')]) }}">
            @csrf

            <div class="mb-6">
                <label for="password" class="mb-1.5 block text-sm font-medium text-ink">Mot de passe</label>
                <input type="password" id="password" name="password" required autofocus class="w-full rounded-control border border-line px-3 py-2 text-ink">
                @error('password')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="min-h-11 w-full rounded-pill bg-ink px-8 py-3 font-medium text-bg">Accéder</button>
        </form>
    </div>
@endsection
