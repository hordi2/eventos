@extends('guest.layout')

@section('title', "Programme d'affiliation — " . config('app.name', 'Itaza Invitation'))

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-16">
        <h1 class="mb-4 text-3xl">Programme d'affiliation</h1>

        <p class="mb-6 text-ink-soft">
            Un programme permettant de recommander Itaza et d'être rémunéré pour chaque organisation apportée n'est
            pas encore en place.
        </p>

        <p class="text-ink-soft">
            Vous animez une communauté d'organisateurs d'événements et souhaitez être informé·e dès son lancement ?
            Écrivez-nous à
            <a href="mailto:{{ config('mail.from.address') }}" class="underline underline-offset-2">{{ config('mail.from.address') }}</a>.
        </p>
    </div>
@endsection
