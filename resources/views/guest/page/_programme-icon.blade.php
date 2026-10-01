{{-- Illustration d'un moment du programme : un trait simple, dessiné en
     SVG dans la page — aucune image à charger, et la couleur suit celle du
     texte, donc le fond sombre comme le fond clair. --}}
@php($icon = $icon ?? 'etoile')
{{-- La couleur se passe explicitement quand le dessin part en image
     (PDF) : currentColor n'y a plus de texte auquel se rapporter. --}}
@php($color = $color ?? 'currentColor')

{{-- xmlns est indispensable : sans lui, dompdf ignore le dessin et le
     feuillet imprimé perd ses illustrations. --}}
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="34" height="34" fill="none" stroke="{{ $color }}" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($icon)
        @case('accueil')
            {{-- Une porte ouverte --}}
            <path d="M12 40V12l16-4v34z" />
            <path d="M28 40h8V14" />
            <circle cx="24" cy="25" r="1.2" fill="{{ $color }}" stroke="none" />
            @break

        @case('ceremonie')
            {{-- Deux alliances entrelacées --}}
            <circle cx="20" cy="28" r="9" />
            <circle cx="28" cy="28" r="9" />
            <path d="M24 14l-3 4h6z" />
            @break

        @case('couple')
            {{-- Deux silhouettes côte à côte --}}
            <circle cx="18" cy="14" r="4" />
            <circle cx="31" cy="14" r="4" />
            <path d="M11 40v-9a7 7 0 0 1 14 0v9" />
            <path d="M25 40v-9a7 7 0 0 1 13-3" />
            @break

        @case('danse')
            {{-- Notes et mouvement --}}
            <circle cx="24" cy="12" r="3" />
            <path d="M24 15v12" />
            <path d="M16 38l8-11 8 11" />
            <path d="M16 22l8 3 8-3" />
            @break

        @case('repas')
            {{-- Couverts --}}
            <path d="M17 8v14a4 4 0 0 0 8 0V8" />
            <path d="M21 22v18" />
            <path d="M33 8c-3 3-3 10 0 12v20" />
            @break

        @case('cadeau')
            {{-- Paquet et ruban --}}
            <rect x="10" y="20" width="28" height="18" rx="2" />
            <path d="M8 20h32v6H8z" />
            <path d="M24 20v18" />
            <path d="M24 20c-5 0-8-2-8-5s5-3 8 5c3-8 8-8 8-5s-3 5-8 5z" />
            @break

        @case('musique')
            {{-- Deux verres qui trinquent --}}
            <path d="M12 10h10l-3 12a4 4 0 0 1-4 0z" />
            <path d="M26 10h10l-3 12a4 4 0 0 1-4 0z" />
            <path d="M17 22v14M31 22v14" />
            <path d="M12 38h10M26 38h10" />
            @break

        @case('discours')
            {{-- Un micro --}}
            <rect x="19" y="8" width="10" height="18" rx="5" />
            <path d="M14 23a10 10 0 0 0 20 0" />
            <path d="M24 33v7M18 40h12" />
            @break

        @case('photo')
            {{-- Un appareil photo --}}
            <rect x="8" y="15" width="32" height="23" rx="3" />
            <path d="M18 15l3-5h6l3 5" />
            <circle cx="24" cy="27" r="6" />
            @break

        @case('fin')
            {{-- Une lune --}}
            <path d="M31 10a15 15 0 1 0 8 23 12 12 0 0 1-8-23z" />
            <path d="M34 14l1 3 3 1-3 1-1 3-1-3-3-1 3-1z" />
            @break

        @default
            {{-- Une étoile, quand rien n'est choisi --}}
            <path d="M24 9l4 11 11 4-11 4-4 11-4-11-11-4 11-4z" />
    @endswitch
</svg>
