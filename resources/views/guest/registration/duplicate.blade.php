@extends('guest.layout')

@section('title', __('Déjà inscrit').' — '.$event->title)

@section('content')
    <div class="mx-auto max-w-lg px-4 py-16 text-center">
        <h1 class="mb-4 text-2xl">{{ __('Vous êtes déjà inscrit') }}</h1>

        <p class="mb-2 text-ink-soft">
            {{ __("Une inscription à :event existe déjà avec l'adresse :email.", ['event' => $event->title, 'email' => $registration->email]) }}
        </p>

        <p class="text-sm text-ink-soft">
            {{ __("Pour la modifier, contactez directement l'organisateur de l'événement.") }}
        </p>
    </div>
@endsection
