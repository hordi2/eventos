@extends('guest.layout')

@section('title', $page->title)

@section('meta')
    <meta name="description" content="{{ $page->metaDescription }}">

    {{-- Open Graph / WhatsApp / Facebook / LinkedIn (AC : image de partage correcte) --}}
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $page->title }}">
    <meta property="og:description" content="{{ $page->metaDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @if ($page->bannerUrl !== null)
        <meta property="og:image" content="{{ $page->bannerUrl }}">
    @endif

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $page->title }}">
    <meta name="twitter:description" content="{{ $page->metaDescription }}">
    @if ($page->bannerUrl !== null)
        <meta name="twitter:image" content="{{ $page->bannerUrl }}">
    @endif

    {{-- schema.org/Event (AC : balisage validé par l'outil Google) --}}
    <script type="application/ld+json">
        {!! json_encode([
            {{-- @@ (et non @) : Blade lit "@context" comme sa propre directive
                (Illuminate\Support\Facades\Context, Laravel 11+) même à
                l'intérieur d'un bloc PHP {!! !!}, la détection de directives
                se fait sur le texte brut avant compilation. --}}
            '@@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $page->title,
            'startDate' => $event->start_at->setTimezone($event->timezone)->toIso8601String(),
            'endDate' => $event->end_at->setTimezone($event->timezone)->toIso8601String(),
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => $page->isOnline
                ? 'https://schema.org/OnlineEventAttendanceMode'
                : 'https://schema.org/OfflineEventAttendanceMode',
            'location' => $page->isOnline
                ? ['@type' => 'VirtualLocation', 'url' => url()->current()]
                : array_filter([
                    '@type' => 'Place',
                    'name' => $page->venueName,
                    'address' => $page->venueAddress,
                    'geo' => $page->venueLatitude !== null && $page->venueLongitude !== null ? [
                        '@type' => 'GeoCoordinates',
                        'latitude' => $page->venueLatitude,
                        'longitude' => $page->venueLongitude,
                    ] : null,
                ]),
            'description' => $page->metaDescription,
            'image' => $page->bannerUrl !== null ? [$page->bannerUrl] : [],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endsection

@section('bodyClass', 'itaza-reading')

@section('content')
    @php($sheets = $page->blocks)
    {{-- Couverture + « L'essentiel » + un feuillet par bloc. --}}
    @php($folioTotal = count($sheets) + 2)
    @php($start = $event->start_at->setTimezone($event->timezone))

    {{-- Progression de lecture : un cheveu en haut de l'écran. --}}
    <div class="itaza-progress" aria-hidden="true"></div>

    {{-- 1. La couverture. --}}
    @include('guest.page._hero', ['page' => $page, 'event' => $event, 'beginUrl' => $beginUrl, 'invitee' => $invitee ?? null])

    {{-- 2. L'essentiel : quand, où, et le mot de l'organisateur — le même
           feuillet que la deuxième page du faire-part. --}}
    <section id="feuillet-2" class="itaza-sheet itaza-sheet-frame">
        <div class="itaza-sheet-inner itaza-section text-center">
            @if ($page->organizationLogoUrl !== null)
                <img src="{{ $page->organizationLogoUrl }}" alt="" loading="lazy" class="mx-auto mb-8 h-12 w-auto object-contain">
            @endif

            <h2 class="mb-6 font-serif text-2xl italic">{{ __("L'essentiel") }}</h2>

            @if ($page->description !== null)
                <p class="mx-auto mb-10 max-w-[32rem] leading-relaxed whitespace-pre-line text-ink">{{ $page->description }}</p>
            @endif

            {{-- La date, en grand, entre deux filets : c'est elle qu'on
                 vient chercher. Le même bloc s'imprime sur le faire-part. --}}
            <p class="font-label text-[0.62rem] tracking-[0.3em] text-ink-soft uppercase">{{ $start->translatedFormat('l') }}</p>

            <div class="mx-auto mt-4 max-w-[26rem] border-y border-line py-6">
                <p class="font-serif leading-none text-ink" style="font-size: clamp(1.5rem, 6vw, 2.25rem)">
                    {{ $start->format('d') }}
                    <span class="uppercase">{{ $start->translatedFormat('F') }}</span>
                    {{ $start->format('Y') }}
                </p>
            </div>

            <p class="mt-5 font-label text-[0.72rem] tracking-[0.3em] text-ink uppercase">{{ $start->format('H\hi') }}</p>

            @if ($page->isOnline || $page->venueName !== null)
                <div class="itaza-rule"></div>

                <p class="font-label text-[0.62rem] tracking-[0.3em] text-ink-soft uppercase">{{ __('Lieu') }}</p>
                <p class="mt-3 font-serif text-xl text-ink">{{ $page->isOnline ? __('En ligne') : $page->venueName }}</p>
                @if (! $page->isOnline && $page->venueAddress !== null)
                    <p class="mx-auto mt-1 max-w-[24rem] text-sm text-ink-soft">{{ $page->venueAddress }}</p>
                @endif
            @endif

            @isset($calendar)
                <div class="mt-10 flex flex-wrap justify-center gap-3 text-sm">
                    <a href="{{ $calendar['google'] }}" target="_blank" rel="noopener" class="inline-flex min-h-10 items-center rounded-pill border border-line px-4 py-2 text-ink">
                        {{ __('Ajouter à Google Agenda') }}
                    </a>
                    <a href="{{ $calendar['ics'] }}" class="inline-flex min-h-10 items-center rounded-pill border border-line px-4 py-2 text-ink">
                        {{ __('Ajouter à mon agenda (.ics)') }}
                    </a>
                </div>
            @endisset
        </div>

        <p class="itaza-folio text-ink-soft">02 / {{ str_pad((string) $folioTotal, 2, '0', STR_PAD_LEFT) }}</p>
    </section>

    {{-- 3. Les blocs composés par l'organisateur, chacun son feuillet. Les
           deux derniers — le code d'entrée puis la confirmation — sont
           toujours là : PageBlocks les ajoute si l'organisateur ne les a pas
           composés lui-même, exactement comme sur le faire-part. --}}
    @foreach ($sheets as $index => $block)
        @include('guest.page._section', [
            'block' => $block,
            'page' => $page,
            'event' => $event,
            'beginUrl' => $beginUrl,
            'invitee' => $invitee ?? null,
            'declineEnabled' => $declineEnabled ?? false,
            'folio' => $index + 3,
            'folioTotal' => $folioTotal,
        ])
    @endforeach

    {{-- Repères de lecture, à droite, sur grand écran : un point par
         feuillet, comme la tranche d'un livret. --}}
    <nav class="itaza-nav" aria-label="{{ __("Feuillets de l'invitation") }}">
        @for ($folio = 1; $folio <= $folioTotal; $folio++)
            <a href="#feuillet-{{ $folio }}" aria-label="{{ __('Feuillet :number', ['number' => $folio]) }}"></a>
        @endfor
    </nav>
@endsection
