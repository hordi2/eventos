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

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-10">
        @if ($page->organizationLogoUrl !== null)
            <img src="{{ $page->organizationLogoUrl }}" alt="" class="mb-4 h-10 w-auto object-contain">
        @endif

        @if ($page->bannerUrl !== null)
            <img src="{{ $page->bannerUrl }}" alt="{{ $page->title }}" class="mb-6 aspect-[2/1] w-full rounded-card object-cover">
        @endif

        <h1 class="mb-1 text-3xl">{{ $page->title }}</h1>
        @if ($page->subtitle !== null)
            <p class="mb-4 text-lg text-ink-soft">{{ $page->subtitle }}</p>
        @endif

        <p class="mb-8 text-sm text-ink-soft">
            {{ $event->start_at->setTimezone($event->timezone)->translatedFormat('d F Y à H:i') }}
            @if (! $page->isOnline && $page->venueName !== null)
                — {{ $page->venueName }}
            @elseif ($page->isOnline)
                — {{ __('En ligne') }}
            @endif
        </p>

        <a
            href="{{ $beginUrl }}"
            class="mb-10 inline-block min-h-11 rounded-pill px-8 py-3 font-medium text-bg {{ $page->organizationPrimaryColor === null ? 'bg-ink' : '' }}"
            @if ($page->organizationPrimaryColor !== null) style="background-color: {{ $page->organizationPrimaryColor }}" @endif
        >
            {{ __("S'inscrire") }}
        </a>

        @if ($page->description !== null)
            <section class="mb-10">
                <h2 class="mb-3 font-serif text-xl italic">{{ __('À propos') }}</h2>
                <p class="whitespace-pre-line text-ink">{{ $page->description }}</p>
            </section>
        @endif

        @isset($calendar)
            <div class="mb-10 flex flex-wrap gap-3 text-sm">
                <a href="{{ $calendar['google'] }}" target="_blank" rel="noopener" class="inline-flex min-h-10 items-center rounded-pill border border-line px-4 py-2 text-ink">
                    {{ __('Ajouter à Google Agenda') }}
                </a>
                <a href="{{ $calendar['ics'] }}" class="inline-flex min-h-10 items-center rounded-pill border border-line px-4 py-2 text-ink">
                    {{ __('Ajouter à mon agenda (.ics)') }}
                </a>
            </div>
        @endisset

        {{-- Page composée par l'organisateur (lot 2) : chaque bloc dans son ordre. --}}
        @foreach ($page->blocks as $block)
            @include('guest.page._block', ['block' => $block, 'page' => $page, 'event' => $event])
        @endforeach

        <a
            href="{{ $beginUrl }}"
            class="inline-block min-h-11 rounded-pill px-8 py-3 font-medium text-bg {{ $page->organizationPrimaryColor === null ? 'bg-ink' : '' }}"
            @if ($page->organizationPrimaryColor !== null) style="background-color: {{ $page->organizationPrimaryColor }}" @endif
        >
            {{ __("S'inscrire") }}
        </a>
    </div>
@endsection
