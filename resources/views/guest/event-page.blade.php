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
    @include('guest.page._hero', ['page' => $page, 'event' => $event, 'beginUrl' => $beginUrl, 'invitee' => $invitee ?? null])

    <div class="mx-auto max-w-2xl px-5 pt-14 sm:pt-20">
        @if ($page->description !== null)
            <section class="itaza-section mb-14 text-center">
                <h2 class="mb-4 font-serif text-2xl italic">{{ __('À propos') }}</h2>
                <p class="mx-auto max-w-[32rem] leading-relaxed whitespace-pre-line text-ink">{{ $page->description }}</p>
            </section>
        @endif
    </div>

    {{-- Page composée par l'organisateur (lot 2) : chaque bloc dans son
         ordre, avec le fond qu'il lui a donné. --}}
    @foreach ($page->blocks as $block)
        @include('guest.page._section', [
            'block' => $block,
            'page' => $page,
            'event' => $event,
            'beginUrl' => $beginUrl,
            'invitee' => $invitee ?? null,
            'declineEnabled' => $declineEnabled ?? false,
        ])
    @endforeach

    <div class="mx-auto max-w-2xl px-5 pb-14 sm:pb-20">
        @isset($calendar)
            <div class="itaza-section mb-14 flex flex-wrap justify-center gap-3 text-sm">
                <a href="{{ $calendar['google'] }}" target="_blank" rel="noopener" class="inline-flex min-h-10 items-center rounded-pill border border-line px-4 py-2 text-ink">
                    {{ __('Ajouter à Google Agenda') }}
                </a>
                <a href="{{ $calendar['ics'] }}" class="inline-flex min-h-10 items-center rounded-pill border border-line px-4 py-2 text-ink">
                    {{ __('Ajouter à mon agenda (.ics)') }}
                </a>
            </div>
        @endisset

        {{-- Dernier appel : l'invité qui a tout lu ne doit pas remonter
             jusqu'à la couverture pour répondre. --}}
        <div class="itaza-section border-t border-line pt-12 text-center">
            <p class="mb-6 font-serif text-2xl italic">{{ __('Nous vous attendons') }}</p>
            <a
                href="{{ $beginUrl }}"
                class="inline-flex min-h-12 items-center justify-center rounded-pill px-10 py-3 font-label text-xs tracking-[0.18em] text-bg uppercase {{ $page->organizationPrimaryColor === null ? 'bg-ink' : '' }}"
                @if ($page->organizationPrimaryColor !== null) style="background-color: {{ $page->organizationPrimaryColor }}" @endif
            >
                {{ $event->type->category() === \App\Domain\Event\Models\EventCategory::Personal ? __("Répondre à l'invitation") : __("S'inscrire") }}
            </a>

            <p class="mt-12 font-label text-[0.68rem] tracking-[0.25em] text-ink-soft uppercase">
                {{ $page->title }}
                @if (! $page->isOnline && $page->venueName !== null) · {{ $page->venueName }} @endif
                · {{ $event->start_at->setTimezone($event->timezone)->translatedFormat('j F Y') }}
            </p>
        </div>
    </div>
@endsection

@section('content')
    @include('guest.page._hero', ['page' => $page, 'event' => $event, 'beginUrl' => $beginUrl, 'invitee' => $invitee ?? null])

    <div class="mx-auto max-w-2xl px-5 py-14 sm:py-20">
        @if ($page->description !== null)
            <section class="itaza-section mb-14 text-center">
                <h2 class="mb-4 font-serif text-2xl italic">{{ __('À propos') }}</h2>
                <p class="mx-auto max-w-[32rem] leading-relaxed whitespace-pre-line text-ink">{{ $page->description }}</p>
            </section>
        @endif

        {{-- Page composée par l'organisateur (lot 2) : chaque bloc dans son ordre. --}}
        @foreach ($page->blocks as $block)
            @include('guest.page._block', [
                'block' => $block,
                'page' => $page,
                'event' => $event,
                'beginUrl' => $beginUrl,
                'invitee' => $invitee ?? null,
                'declineEnabled' => $declineEnabled ?? false,
            ])
        @endforeach

        @isset($calendar)
            <div class="itaza-section mb-14 flex flex-wrap justify-center gap-3 text-sm">
                <a href="{{ $calendar['google'] }}" target="_blank" rel="noopener" class="inline-flex min-h-10 items-center rounded-pill border border-line px-4 py-2 text-ink">
                    {{ __('Ajouter à Google Agenda') }}
                </a>
                <a href="{{ $calendar['ics'] }}" class="inline-flex min-h-10 items-center rounded-pill border border-line px-4 py-2 text-ink">
                    {{ __('Ajouter à mon agenda (.ics)') }}
                </a>
            </div>
        @endisset

        {{-- Dernier appel : l'invité qui a tout lu ne doit pas remonter
             jusqu'à la couverture pour répondre. --}}
        <div class="itaza-section border-t border-line pt-12 text-center">
            <p class="mb-6 font-serif text-2xl italic">{{ __('Nous vous attendons') }}</p>
            <a
                href="{{ $beginUrl }}"
                class="inline-flex min-h-12 items-center justify-center rounded-pill px-10 py-3 font-label text-xs tracking-[0.18em] text-bg uppercase {{ $page->organizationPrimaryColor === null ? 'bg-ink' : '' }}"
                @if ($page->organizationPrimaryColor !== null) style="background-color: {{ $page->organizationPrimaryColor }}" @endif
            >
                {{ $event->type->category() === \App\Domain\Event\Models\EventCategory::Personal ? __("Répondre à l'invitation") : __("S'inscrire") }}
            </a>

            <p class="mt-12 font-label text-[0.68rem] tracking-[0.25em] text-ink-soft uppercase">
                {{ $page->title }}
                @if (! $page->isOnline && $page->venueName !== null) · {{ $page->venueName }} @endif
                · {{ $event->start_at->setTimezone($event->timezone)->translatedFormat('j F Y') }}
            </p>
        </div>
    </div>
@endsection
