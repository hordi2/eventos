@extends('guest.layout')

@section('title', "Inscription annulée — {$event->title}")

@section('content')
    <div class="mx-auto max-w-lg px-4 py-16 text-center">
        <h1 class="mb-4 text-2xl">Inscription annulée</h1>
        <p class="text-ink-soft">Votre inscription à {{ $event->title }} a été annulée. Votre place a été libérée.</p>
    </div>
@endsection
