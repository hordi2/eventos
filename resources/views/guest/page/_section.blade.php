{{-- Un feuillet de l'invitation : un écran par bloc, dans le même ordre et
     avec le même fond que la page correspondante du faire-part imprimé.
     L'image ou la couleur choisie par l'organisateur couvre le feuillet
     entier ; le contenu, lui, garde la colonne de lecture. --}}
@php
    $background = $block['background'] ?? null;
    $backgroundColor = $block['backgroundColor'] ?? null;
    $onDark = ($block['textTone'] ?? 'dark') === 'light';
    $overlay = min(90, max(0, (int) ($block['backgroundOverlay'] ?? 45))) / 100;
    // La photo pleine page occupe le feuillet jusqu'aux bords : ni marge,
    // ni filet, ni numéro par-dessus l'image.
    $bleed = $block['type'] === 'full_photo';
    $classes = 'itaza-sheet'
        .($bleed ? ' itaza-sheet--bleed' : '')
        .($onDark ? ' itaza-on-dark' : '')
        .(! $background && ! $bleed ? ' itaza-sheet-frame' : '');
    $inner = ['block' => $block, 'page' => $page, 'event' => $event, 'beginUrl' => $beginUrl, 'invitee' => $invitee ?? null, 'declineEnabled' => $declineEnabled ?? false];
@endphp

<section
    id="feuillet-{{ $folio }}"
    class="{{ $classes }}"
    @if ($backgroundColor) style="background-color: {{ $backgroundColor }}" @endif
>
    @if ($background)
        <img
            src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($background) }}"
            alt=""
            loading="lazy"
            decoding="async"
            class="absolute inset-0 -z-20 h-full w-full object-cover"
        >
        <div aria-hidden="true" class="absolute inset-0 -z-10" style="background-color: rgba(20, 16, 12, {{ $overlay }})"></div>
    @endif

    <div class="itaza-sheet-inner">
        @include('guest.page._block', $inner)
    </div>

    @unless ($bleed)
        {{-- Numéro de feuillet, comme la pagination d'un faire-part. --}}
        <p class="itaza-folio text-ink-soft">{{ str_pad((string) $folio, 2, '0', STR_PAD_LEFT) }} / {{ str_pad((string) $folioTotal, 2, '0', STR_PAD_LEFT) }}</p>
    @endunless
</section>
