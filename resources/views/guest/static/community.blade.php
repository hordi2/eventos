@extends('guest.layout')

@section('title', 'Communauté — ' . config('app.name', 'Itaza Invitation'))

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-16">
        <h1 class="mb-4 text-3xl">Communauté</h1>

        <p class="mb-6 text-ink-soft">
            Un espace d'échange entre organisateurs — questions, retours d'expérience, bonnes pratiques — n'existe pas
            encore sous forme de forum. En attendant, nous restons directement joignables.
        </p>

        <p class="text-ink-soft">
            Une idée, une suggestion, envie d'échanger avec d'autres organisateurs qui utilisent Itaza ? Écrivez-nous à
            <a href="mailto:{{ config('mail.from.address') }}" class="underline underline-offset-2">{{ config('mail.from.address') }}</a>.
        </p>
    </div>
@endsection
