{{-- Un bloc de la page publique (PageBlocks). Le titre est facultatif :
     sans lui, le bloc garde son titre d'origine quand il en a un. --}}
@php($blockTitle = $block['title'] ?? null)

@switch($block['type'])
    @case('text')
        <section class="mb-10">
            @if ($blockTitle)
                <h2 class="mb-3 font-serif text-xl italic">{{ $blockTitle }}</h2>
            @endif
            <p class="whitespace-pre-line text-ink">{{ $block['body'] ?? '' }}</p>
        </section>
        @break

    @case('image')
        @if (! empty($block['path']))
            <figure class="mb-10">
                <img
                    src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($block['path']) }}"
                    alt="{{ $block['alt'] ?? '' }}"
                    loading="lazy"
                    class="w-full rounded-card"
                >
                @if ($blockTitle)
                    <figcaption class="mt-2 text-sm text-ink-soft">{{ $blockTitle }}</figcaption>
                @endif
            </figure>
        @endif
        @break

    @case('video')
        @php($video = \App\Domain\Form\Support\VideoEmbed::from($block['url'] ?? null))
        @if ($video !== null)
            <section class="mb-10">
                @if ($blockTitle)
                    <h2 class="mb-3 font-serif text-xl italic">{{ $blockTitle }}</h2>
                @endif
                {{-- Le lecteur n'arrive qu'au clic : la page reste légère. --}}
                <div x-data="{ playing: false }" class="overflow-hidden rounded-card border border-line">
                    <template x-if="playing">
                        <div class="aspect-video w-full">
                            <iframe
                                src="{{ $video->embedUrl }}"
                                title="{{ $blockTitle ?? $page->title }}"
                                loading="lazy"
                                allow="accelerometer; autoplay; encrypted-media; picture-in-picture"
                                allowfullscreen
                                class="h-full w-full"
                            ></iframe>
                        </div>
                    </template>
                    <button type="button" x-show="! playing" @click="playing = true" class="flex min-h-11 w-full items-center gap-3 px-4 py-3 text-left">
                        <span aria-hidden="true" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-ink text-bg">▶</span>
                        <span>
                            <span class="block text-sm font-medium text-ink">{{ __('Lire la vidéo') }}</span>
                            <span class="block text-xs text-ink-soft">{{ $video->provider }} · {{ __('se charge seulement quand vous cliquez') }}</span>
                        </span>
                    </button>
                </div>
            </section>
        @endif
        @break

    @case('program')
        @if (($block['items'] ?? []) !== [])
            <section class="mb-10">
                <h2 class="mb-3 font-serif text-xl italic">{{ $blockTitle ?? __('Programme') }}</h2>
                <ul class="space-y-3">
                    @foreach ($block['items'] as $item)
                        <li class="flex gap-4">
                            <span class="w-16 shrink-0 font-medium">{{ $item['time'] ?? '' }}</span>
                            <div>
                                <p class="font-medium">{{ $item['title'] ?? '' }}</p>
                                @if (! empty($item['description']))
                                    <p class="text-sm text-ink-soft">{{ $item['description'] }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
        @break

    @case('faq')
        @if (($block['items'] ?? []) !== [])
            <section class="mb-10">
                <h2 class="mb-3 font-serif text-xl italic">{{ $blockTitle ?? __('Questions fréquentes') }}</h2>
                <div class="space-y-2">
                    @foreach ($block['items'] as $item)
                        <div x-data="{ open: false }" class="rounded-card bg-bg ring-1 ring-line">
                            <button type="button" x-on:click="open = !open" class="flex w-full items-center justify-between px-4 py-3 text-left font-medium">
                                {{ $item['question'] ?? '' }}
                                <span x-text="open ? '−' : '+'"></span>
                            </button>
                            <p x-show="open" x-cloak class="px-4 pb-3 text-sm text-ink-soft">{{ $item['answer'] ?? '' }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
        @break

    @case('venue')
        @if (! $page->isOnline && $page->venueName !== null)
            <section class="mb-10">
                <h2 class="mb-3 font-serif text-xl italic">{{ $blockTitle ?? __('Lieu') }}</h2>
                <p class="mb-3">{{ $page->venueName }}</p>
                @if ($page->venueAddress !== null)
                    <p class="mb-3 text-sm text-ink-soft">{{ $page->venueAddress }}</p>
                @endif
                @if ($page->venueLatitude !== null && $page->venueLongitude !== null)
                    <iframe
                        class="h-64 w-full rounded-card"
                        loading="lazy"
                        src="https://www.openstreetmap.org/export/embed.html?bbox={{ $page->venueLongitude - 0.01 }}%2C{{ $page->venueLatitude - 0.01 }}%2C{{ $page->venueLongitude + 0.01 }}%2C{{ $page->venueLatitude + 0.01 }}&marker={{ $page->venueLatitude }}%2C{{ $page->venueLongitude }}"
                        title="{{ __('Carte du lieu') }}"
                    ></iframe>
                @endif
            </section>
        @endif
        @break

    @case('countdown')
        {{-- Compte à rebours : calculé chez l'invité, à partir de l'instant
             de début envoyé en UTC — jamais l'heure du serveur. --}}
        <section
            class="mb-10 rounded-card bg-bg px-4 py-6 text-center ring-1 ring-line"
            x-data="{
                left: '',
                tick() {
                    const total = Math.max(0, Math.floor((new Date('{{ $event->start_at->toIso8601String() }}') - new Date()) / 1000));
                    const days = Math.floor(total / 86400);
                    const hours = Math.floor((total % 86400) / 3600);
                    const minutes = Math.floor((total % 3600) / 60);
                    this.left = total === 0 ? '{{ __("C'est aujourd'hui !") }}' : `${days} {{ __('j') }} ${hours} {{ __('h') }} ${minutes} {{ __('min') }}`;
                },
            }"
            x-init="tick(); setInterval(() => tick(), 30000)"
        >
            <h2 class="mb-2 font-serif text-xl italic">{{ $blockTitle ?? __('Compte à rebours') }}</h2>
            <p class="text-2xl font-medium text-ink" x-text="left">—</p>
        </section>
        @break
@endswitch
