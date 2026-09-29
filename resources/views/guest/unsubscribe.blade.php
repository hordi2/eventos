@extends('guest.layout')

@section('title', __('Désabonnement').' — '.$organization->name)

@section('content')
    <div class="mx-auto max-w-lg px-4 py-16 text-center">
        <h1 class="mb-4 text-2xl">{{ __('Vous êtes désabonné') }}</h1>

        <p class="text-ink-soft">
            {{ __("Vous ne recevrez plus d'e-mails de :organization, à l'exception des messages liés à une inscription en cours.", ['organization' => $organization->name]) }}
        </p>
    </div>
@endsection
