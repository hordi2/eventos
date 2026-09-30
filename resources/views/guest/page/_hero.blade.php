{{-- Couverture de l'invitation : la photo occupe tout l'écran, le texte se
     lit par-dessus. Volontairement la même composition sur téléphone et sur
     ordinateur — une colonne centrée, dont la typographie grandit avec
     clamp() : une invitation doit se reconnaître d'un support à l'autre.

     Sans photo, la couverture garde sa mise en page sur un fond d'encre :
     jamais d'écran vide, jamais de trou dans la composition. --}}
@php
    $start = $event->start_at->setTimezone($event->timezone);
    $isPersonal = $event->type->category() === \App\Domain\Event\Models\EventCategory::Personal;
    $place = $page->isOnline ? __('En ligne') : $page->venueName;
@endphp

<header class="itaza-cover relative isolate flex min-h-[100svh] flex-col justify-center overflow-hidden bg-ink px-5 py-16 text-center">
    @if ($page->bannerUrl !== null)
        {{-- fetchpriority : c'est la première chose que voit l'invité, et la
             seule image de cet écran (budget 3G du CLAUDE.md §2). --}}
        <img
            src="{{ $page->bannerUrl }}"
            alt=""
            fetchpriority="high"
            class="absolute inset-0 -z-20 h-full w-full object-cover"
        >
        {{-- Deux voiles plutôt qu'un : un voile uni garantit la lisibilité
             partout sur la photo, le dégradé ancre le texte en bas et
             adoucit le haut. --}}
        <div aria-hidden="true" class="absolute inset-0 -z-10 bg-black/50"></div>
        <div aria-hidden="true" class="absolute inset-0 -z-10 bg-gradient-to-b from-black/40 via-transparent to-black/70"></div>
    @endif

    <div class="mx-auto flex w-full max-w-[34rem] flex-col items-center">
        @if ($page->organizationLogoUrl !== null)
            <img src="{{ $page->organizationLogoUrl }}" alt="" class="mb-8 h-12 w-auto object-contain">
        @endif

        <p class="mb-6 font-label text-[0.68rem] tracking-[0.3em] text-white/80 uppercase">
            {{ $isPersonal ? __('Vous êtes invité') : __('Invitation') }}
        </p>

        <h1 class="font-serif leading-[0.95] text-white" style="font-size: clamp(2.75rem, 11vw, 4.75rem)">
            {{ $page->title }}
        </h1>

        @if ($page->subtitle !== null)
            <p class="mt-5 max-w-[26rem] font-serif text-lg text-white/85 italic sm:text-xl">{{ $page->subtitle }}</p>
        @endif

        {{-- La date, en trois temps : le jour se lit de loin, le mois et
             l'année l'encadrent — c'est la signature des faire-part. --}}
        <div class="mt-10 flex w-full max-w-[22rem] items-center justify-center gap-4 border-y border-white/25 py-4 text-white">
            <span class="font-serif text-3xl leading-none">{{ $start->format('d') }}</span>
            <span class="font-label text-xs tracking-[0.25em] uppercase">{{ $start->translatedFormat('F') }}</span>
            <span class="font-serif text-3xl leading-none">{{ $start->format('Y') }}</span>
        </div>

        <p class="mt-4 font-label text-[0.68rem] tracking-[0.25em] text-white/80 uppercase">
            {{ $start->format('H\hi') }}@if ($place) · {{ $place }}@endif
        </p>

        <a
            href="{{ $beginUrl }}"
            class="mt-10 inline-flex min-h-12 items-center justify-center rounded-pill px-10 py-3 font-label text-xs tracking-[0.18em] text-ink uppercase {{ $page->organizationPrimaryColor === null ? 'bg-white' : 'text-bg' }}"
            @if ($page->organizationPrimaryColor !== null) style="background-color: {{ $page->organizationPrimaryColor }}" @endif
        >
            {{ $isPersonal ? __("Répondre à l'invitation") : __("S'inscrire") }}
        </a>
    </div>
</header>
