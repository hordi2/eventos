@extends('guest.layout')

@section('title', "Inscription mise à jour — {$event->title}")

@section('content')
    <div class="mx-auto max-w-lg px-4 py-16 text-center">
        <h1 class="mb-4 text-2xl">Modifications enregistrées</h1>
        <p class="text-ink-soft">Votre inscription à {{ $event->title }} a bien été mise à jour.</p>
    </div>
@endsection
