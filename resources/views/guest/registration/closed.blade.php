@extends('guest.layout')

@section('title', "Inscriptions fermées — {$event->title}")

@section('content')
    <div class="mx-auto max-w-lg px-4 py-16 text-center">
        <h1 class="mb-4 text-2xl">{{ $event->title }}</h1>

        <p class="text-ink-soft">
            @if ($reason === 'full')
                Cet événement affiche complet.
            @else
                {{ $event->registration_closed_message ?? "Les inscriptions ne sont pas ouvertes pour le moment." }}
            @endif
        </p>
    </div>
@endsection
