@extends('guest.static-layout')

@section('title', "Retour d'information — " . config('app.name', 'Itaza Invitation'))

@php
    $types = [
        [
            'title' => 'Un bug',
            'body' => "Ce que vous essayiez de faire, ce qui s'est passé à la place, et si possible l'événement concerné.",
            'accent' => 'bg-danger/10 text-danger',
            'icon' => 'M12 9v4m0 4h.01M10.3 3.9 2.5 17a2 2 0 0 0 1.7 3h15.6a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z',
        ],
        [
            'title' => 'Une idée de fonctionnalité',
            'body' => "Le besoin concret derrière votre idée — cela nous aide souvent à trouver une solution plus simple que celle imaginée au départ.",
            'accent' => 'bg-accent/10 text-accent',
            'icon' => 'M9 18h6M10 21h4M12 3a6 6 0 0 0-3.6 10.8c.6.5 1.1 1.3 1.1 2.2h5c0-.9.5-1.7 1.1-2.2A6 6 0 0 0 12 3Z',
        ],
        [
            'title' => 'Un retour général',
            'body' => "Ce qui a bien ou mal fonctionné pour votre événement, sans besoin d'action immédiate de notre part.",
            'accent' => 'bg-success/10 text-success',
            'icon' => 'M8 10h8M8 14h5M21 12a9 9 0 1 1-9-9 9 9 0 0 1 9 9Z',
        ],
    ];
@endphp

@section('content')
    <div class="border-b border-line bg-accent/5">
        <div class="mx-auto max-w-2xl px-4 py-16 sm:px-6">
            <p class="mb-3 font-label text-xs tracking-[0.28em] text-accent uppercase">Retour d'information</p>
            <h1 class="mb-4 font-serif text-4xl text-ink italic">Votre avis nous intéresse</h1>
            <p class="text-ink-soft">
                Une fonctionnalité qui vous manque, un détail qui vous a gêné, quelque chose qui a particulièrement
                bien fonctionné pour votre événement : tout retour nous aide à améliorer Itaza.
            </p>
        </div>
    </div>

    <div class="mx-auto max-w-2xl px-4 py-16 sm:px-6">
        <p class="mb-6 font-medium text-ink">Trois types de retours nous sont particulièrement utiles :</p>

        <ul class="mb-12 space-y-3">
            @foreach ($types as $type)
                <li class="flex gap-4 rounded-card border border-line p-5">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $type['accent'] }}">
                        <svg viewBox="0 0 24 24" class="h-5 w-5 stroke-current fill-none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <path d="{{ $type['icon'] }}" />
                        </svg>
                    </span>
                    <div>
                        <p class="mb-1 font-medium text-ink">{{ $type['title'] }}</p>
                        <p class="text-sm text-ink-soft">{{ $type['body'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="rounded-card bg-bg-alt p-6 text-center">
            <p class="mb-2 font-medium text-ink">Prêt·e à nous écrire ?</p>
            <a
                href="mailto:{{ config('mail.from.address') }}"
                class="inline-flex rounded-pill bg-ink px-6 py-3 text-sm text-bg"
            >
                {{ config('mail.from.address') }}
            </a>
            <p class="mt-3 text-sm text-ink-soft">Nous lisons chaque message.</p>
        </div>
    </div>
@endsection
