@extends('guest.layout')

@section('title', "Modification impossible — {$event->title}")

@section('content')
    <div class="mx-auto max-w-lg px-4 py-16 text-center">
        <h1 class="mb-4 text-2xl">{{ $event->title }}</h1>
        <p class="text-ink-soft">La modification de cette inscription n'est plus possible.</p>
    </div>
@endsection
