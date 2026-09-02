@extends('guest.layout')

@section('title', "Déjà inscrit — {$event->title}")

@section('content')
    <div class="mx-auto max-w-lg px-4 py-16 text-center">
        <h1 class="mb-4 text-2xl">Vous êtes déjà inscrit</h1>

        <p class="mb-2 text-ink-soft">
            Une inscription à {{ $event->title }} existe déjà avec l'adresse {{ $registration->email }}.
        </p>

        <p class="text-sm text-ink-soft">
            Pour la modifier, contactez directement l'organisateur de l'événement.
        </p>
    </div>
@endsection
