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
                — En ligne
            @endif
        </p>

        <a href="{{ $beginUrl }}" class="mb-10 inline-block min-h-11 rounded-pill bg-ink px-8 py-3 font-medium text-bg">
            S'inscrire
        </a>

        @if ($page->description !== null)
            <section class="mb-10">
                <h2 class="mb-3 font-serif text-xl italic">À propos</h2>
                <p class="whitespace-pre-line text-ink">{{ $page->description }}</p>
            </section>
        @endif

        @if ($page->programItems !== [])
            <section class="mb-10">
                <h2 class="mb-3 font-serif text-xl italic">Programme</h2>
                <ul class="space-y-3">
                    @foreach ($page->programItems as $item)
                        <li class="flex gap-4">
                            <span class="w-16 shrink-0 font-medium">{{ $item['time'] }}</span>
                            <div>
                                <p class="font-medium">{{ $item['title'] }}</p>
                                @if (! empty($item['description']))
                                    <p class="text-sm text-ink-soft">{{ $item['description'] }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if (! $page->isOnline && $page->venueName !== null)
            <section class="mb-10">
                <h2 class="mb-3 font-serif text-xl italic">Lieu</h2>
                <p class="mb-3">{{ $page->venueName }}</p>
                @if ($page->venueAddress !== null)
                    <p class="mb-3 text-sm text-ink-soft">{{ $page->venueAddress }}</p>
                @endif
                @if ($page->venueLatitude !== null && $page->venueLongitude !== null)
                    <iframe
                        class="h-64 w-full rounded-card"
                        loading="lazy"
                        src="https://www.openstreetmap.org/export/embed.html?bbox={{ $page->venueLongitude - 0.01 }}%2C{{ $page->venueLatitude - 0.01 }}%2C{{ $page->venueLongitude + 0.01 }}%2C{{ $page->venueLatitude + 0.01 }}&marker={{ $page->venueLatitude }}%2C{{ $page->venueLongitude }}"
                        title="Carte du lieu"
                    ></iframe>
                @endif
            </section>
        @endif

        @if ($page->faqItems !== [])
            <section class="mb-10">
                <h2 class="mb-3 font-serif text-xl italic">Questions fréquentes</h2>
                <div class="space-y-2">
                    @foreach ($page->faqItems as $item)
                        <div x-data="{ open: false }" class="rounded-card bg-bg ring-1 ring-line">
                            <button type="button" x-on:click="open = !open" class="flex w-full items-center justify-between px-4 py-3 text-left font-medium">
                                {{ $item['question'] }}
                                <span x-text="open ? '−' : '+'"></span>
                            </button>
                            <p x-show="open" x-cloak class="px-4 pb-3 text-sm text-ink-soft">{{ $item['answer'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <a href="{{ $beginUrl }}" class="inline-block min-h-11 rounded-pill bg-ink px-8 py-3 font-medium text-bg">
            S'inscrire
        </a>
    </div>
@endsection
