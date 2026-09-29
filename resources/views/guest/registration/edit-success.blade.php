@extends('guest.layout')

@section('title', __('Inscription mise à jour').' — '.$event->title)

@section('content')
    <div class="mx-auto max-w-lg px-4 py-16 text-center">
        <h1 class="mb-4 text-2xl">{{ __('Modifications enregistrées') }}</h1>
        <p class="text-ink-soft">{{ __('Votre inscription à :event a bien été mise à jour.', ['event' => $event->title]) }}</p>
    </div>
@endsection
