@extends('guest.layout')

@section('title', __('Badge scanné'))

@section('content')
    <div class="mx-auto max-w-lg px-4 py-20 text-center">
        <h1 class="mb-4 text-2xl">{{ __('On ne sait pas encore qui vous êtes') }}</h1>
        <p class="mb-8 text-ink-soft">
            {{ __('Ouvrez d’abord votre propre lien d’annuaire — celui reçu à votre inscription — puis scannez de nouveau ce badge : votre rencontre sera enregistrée des deux côtés.') }}
        </p>
        <p class="font-label text-[0.68rem] tracking-[0.25em] text-ink-soft uppercase">{{ $event->title }}</p>
    </div>
@endsection
