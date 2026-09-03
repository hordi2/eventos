@extends('guest.layout')

@section('title', "Désabonnement — {$organization->name}")

@section('content')
    <div class="mx-auto max-w-lg px-4 py-16 text-center">
        <h1 class="mb-4 text-2xl">Vous êtes désabonné</h1>

        <p class="text-ink-soft">
            Vous ne recevrez plus d'e-mails de {{ $organization->name }}, à l'exception des messages liés à une
            inscription en cours.
        </p>
    </div>
@endsection
