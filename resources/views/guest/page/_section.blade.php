{{-- Un bloc de la page, avec son fond. L'image ou la couleur choisie par
     l'organisateur court sur toute la largeur ; le contenu, lui, garde la
     colonne de lecture. Sans fond, le bloc reste sur le fond de la page. --}}
@php
    $background = $block['background'] ?? null;
    $backgroundColor = $block['backgroundColor'] ?? null;
    $onDark = ($block['textTone'] ?? 'dark') === 'light';
    $overlay = min(90, max(0, (int) ($block['backgroundOverlay'] ?? 45))) / 100;
    $inner = ['block' => $block, 'page' => $page, 'event' => $event, 'beginUrl' => $beginUrl, 'invitee' => $invitee ?? null, 'declineEnabled' => $declineEnabled ?? false];
@endphp

@if ($background || $backgroundColor)
    <div
        class="relative isolate overflow-hidden {{ $onDark ? 'itaza-on-dark' : '' }}"
        @if ($backgroundColor) style="background-color: {{ $backgroundColor }}" @endif
    >
        @if ($background)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($background) }}" alt="" loading="lazy" class="absolute inset-0 -z-20 h-full w-full object-cover">
            <div aria-hidden="true" class="absolute inset-0 -z-10" style="background-color: rgba(20, 16, 12, {{ $overlay }})"></div>
        @endif

        <div class="mx-auto max-w-2xl px-5 pt-16">
            @include('guest.page._block', $inner)
        </div>
    </div>
@else
    <div class="mx-auto max-w-2xl px-5">
        @include('guest.page._block', $inner)
    </div>
@endif
