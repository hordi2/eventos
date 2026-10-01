{{-- Couverture de l'invitation : la photo occupe tout l'écran, le texte se
     lit par-dessus. Volontairement la même composition sur téléphone et sur
     ordinateur — une colonne centrée, dont la typographie grandit avec
     clamp() : une invitation doit se reconnaître d'un support à l'autre.

     Sans photo, la couverture garde sa mise en page sur un fond d'encre :
     jamais d'écran vide, jamais de trou dans la composition.

     Tout ce qui s'y lit vient de l'organisateur (réglages de la page) : la
     phrase d'ouverture, le mot manuscrit, le monogramme, l'intensité du
     voile et le libellé du bouton. --}}
@php
    $start = $event->start_at->setTimezone($event->timezone);
    $isPersonal = $event->type->category() === \App\Domain\Event\Models\EventCategory::Personal;
    $place = $page->isOnline ? __('En ligne') : $page->venueName;
    $eyebrow = $page->coverEyebrow ?: ($isPersonal ? __('Vous êtes invité') : __('Invitation'));
    $ctaLabel = $page->coverCtaLabel ?: ($isPersonal ? __("Répondre à l'invitation") : __("S'inscrire"));
@endphp

<header class="itaza-cover relative isolate flex min-h-[100svh] flex-col justify-center overflow-hidden bg-ink px-5 py-16 text-center">
    @if ($page->bannerUrl !== null)
        {{-- fetchpriority : c'est la première chose que voit l'invité, et la
             seule image de cet écran (budget 3G du CLAUDE.md §2). Le très
             lent zoom arrière donne vie à la photo sans rien demander à
             l'invité ; il s'arrête si son appareil réclame moins d'animation. --}}
        <img
            src="{{ $page->bannerUrl }}"
            alt=""
            fetchpriority="high"
            class="itaza-cover-photo absolute inset-0 -z-20 h-full w-full object-cover"
        >
        {{-- Deux voiles plutôt qu'un : un voile uni, dont l'organisateur
             règle l'intensité, garantit la lisibilité partout sur la photo ;
             le dégradé ancre le texte en bas et adoucit le haut. --}}
        <div aria-hidden="true" class="absolute inset-0 -z-10" style="background-color: rgba(20, 16, 12, {{ min(90, max(0, $page->coverOverlay)) / 100 }})"></div>
        <div aria-hidden="true" class="absolute inset-0 -z-10 bg-gradient-to-b from-black/35 via-transparent to-black/60"></div>
    @endif

    @if ($page->coverMonogram)
        {{-- Monogramme en filigrane, comme sur un faire-part imprimé. --}}
        <span aria-hidden="true" class="itaza-monogram pointer-events-none absolute inset-0 -z-10 flex items-center justify-center font-serif leading-none text-white/[0.07]" style="font-size: clamp(16rem, 60vw, 40rem)">
            {{ $page->coverMonogram }}
        </span>
    @endif

    <div class="mx-auto flex w-full max-w-[34rem] flex-col items-center">
        @if ($page->organizationLogoUrl !== null)
            <img src="{{ $page->organizationLogoUrl }}" alt="" class="itaza-reveal mb-8 h-12 w-auto object-contain">
        @endif

        <p class="itaza-reveal mb-6 font-label text-[0.68rem] tracking-[0.3em] text-white/80 uppercase" style="--itaza-delay: 80ms">
            {{ $eyebrow }}
        </p>

        @if ($page->coverScript)
            <p class="itaza-reveal mb-3 font-serif text-white italic" style="font-size: clamp(1.75rem, 6vw, 2.5rem); --itaza-delay: 140ms">
                {{ $page->coverScript }}
            </p>
        @endif

        <h1 class="itaza-reveal font-serif leading-[0.95] text-white" style="font-size: clamp(2.75rem, 11vw, 4.75rem); --itaza-delay: 200ms">
            {{ $page->title }}
        </h1>

        @if ($page->subtitle !== null)
            <p class="itaza-reveal mt-5 max-w-[26rem] font-serif text-lg text-white/85 italic sm:text-xl" style="--itaza-delay: 280ms">{{ $page->subtitle }}</p>
        @endif

        {{-- La date, en trois temps : le jour se lit de loin, le mois et
             l'année l'encadrent — c'est la signature des faire-part. --}}
        <div class="itaza-reveal mt-10 flex w-full max-w-[22rem] items-center justify-center gap-4 border-y border-white/25 py-4 text-white" style="--itaza-delay: 360ms">
            <span class="font-serif text-3xl leading-none">{{ $start->format('d') }}</span>
            <span class="font-label text-xs tracking-[0.25em] uppercase">{{ $start->translatedFormat('F') }}</span>
            <span class="font-serif text-3xl leading-none">{{ $start->format('Y') }}</span>
        </div>

        <p class="itaza-reveal mt-4 font-label text-[0.68rem] tracking-[0.25em] text-white/80 uppercase" style="--itaza-delay: 420ms">
            {{ $start->format('H\hi') }}@if ($place) · {{ $place }}@endif
        </p>

        <a
            href="{{ $beginUrl }}"
            class="itaza-reveal mt-10 inline-flex min-h-12 items-center justify-center rounded-pill px-10 py-3 font-label text-xs tracking-[0.18em] text-ink uppercase transition-transform duration-300 hover:scale-[1.03] {{ $page->organizationPrimaryColor === null ? 'bg-white' : 'text-bg' }}"
            style="--itaza-delay: 500ms{{ $page->organizationPrimaryColor !== null ? '; background-color: '.$page->organizationPrimaryColor : '' }}"
        >
            {{ $ctaLabel }}
        </a>
    </div>

    {{-- Invitation à descendre : le reste de la page n'existe pas tant que
         l'invité ne sait pas qu'il peut faire défiler. --}}
    <span aria-hidden="true" class="itaza-scroll-hint absolute inset-x-0 bottom-8 flex justify-center text-white/60">
        <svg width="18" height="28" viewBox="0 0 18 28" fill="none" stroke="currentColor" stroke-width="1.2">
            <rect x="0.6" y="0.6" width="16.8" height="26.8" rx="8.4" />
            <line class="itaza-scroll-dot" x1="9" y1="7" x2="9" y2="11" stroke-linecap="round" />
        </svg>
    </span>
</header>
