@extends('guest.layout')

@section('title', "Retour d'information — " . config('app.name', 'Itaza Invitation'))

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-16">
        <h1 class="mb-4 text-3xl">Votre avis nous intéresse</h1>

        <p class="mb-6 text-ink-soft">
            Une fonctionnalité qui vous manque, un détail qui vous a gêné, quelque chose qui a particulièrement bien
            fonctionné pour votre événement : tout retour nous aide à améliorer Itaza.
        </p>

        <p class="text-ink-soft">
            Écrivez-nous à
            <a href="mailto:{{ config('mail.from.address') }}" class="underline underline-offset-2">{{ config('mail.from.address') }}</a>
            — précisez si possible ce que vous avez essayé de faire et ce qui vous a bloqué, cela nous permet d'agir
            plus vite.
        </p>
    </div>
@endsection
